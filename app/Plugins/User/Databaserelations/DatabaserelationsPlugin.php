<?php

namespace App\Plugins\User\Databaserelations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User\DatabaseRelations\DatabasesRelations;
use App\Models\User\DatabaseRelations\DatabasesRelationValues;
use App\Models\User\Databases\Databases;
use App\Models\User\Databases\DatabasesColumns;
use App\Models\User\Databases\DatabasesInputCols;
use App\Models\User\Databases\DatabasesInputs;
use App\Models\Core\FrameConfig;
use App\Plugins\User\Databaserelations\Services\DatabaseRelationService;
use App\Plugins\User\UserPluginBase;

class DatabaserelationsPlugin extends UserPluginBase
{
    public $use_getpost = false;

    public function getPublicFunctions()
    {
        return [
            'get' => ['index', 'editRelations', 'editRecordRelations'],
            'post' => ['saveFrameDatabase', 'saveRelation', 'deleteRelation', 'saveRecordRelations'],
        ];
    }

    public function declareRole()
    {
        return [
            'editRelations' => ['frames.edit'],
            'saveFrameDatabase' => ['frames.edit'],
            'saveRelation' => ['frames.edit'],
            'deleteRelation' => ['frames.delete'],
            'editRecordRelations' => ['frames.edit'],
            'saveRecordRelations' => ['frames.edit'],
        ];
    }

    public function index($request, $page_id, $frame_id)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $database = $databases_id ? Databases::find($databases_id) : null;
        $service = new DatabaseRelationService();
        $relations = $databases_id ? $service->getRelations($databases_id) : collect();
        $database_frame = $databases_id ? $service->resolveDatabaseFrameForRelations($databases_id, $relations) : null;

        if ($databases_id) {
            $service->cleanupOrphanValues($databases_id);
        }

        $source_inputs = $databases_id
            ? DatabasesInputs::where('databases_id', $databases_id)->orderBy('display_sequence')->orderBy('id')->get()
            : collect();
        $source_column = $this->getDefaultDisplayColumn($databases_id);
        $source_labels = $this->getRecordLabels($source_inputs, $source_column);

        $relation_display = collect();
        foreach ($relations as $relation) {
            $side = $relation->sideForDatabase($databases_id);
            $other_database_id = $side === 'one' ? $relation->many_database_id : $relation->one_database_id;
            $other_column_id = $side === 'one' ? $relation->many_display_column_id : $relation->one_display_column_id;
            $other_inputs = DatabasesInputs::where('databases_id', $other_database_id)->get();
            $other_labels = $this->getRecordLabels(
                $other_inputs,
                $other_column_id ? DatabasesColumns::find($other_column_id) : $this->getDefaultDisplayColumn($other_database_id)
            );

            $per_record = collect();
            foreach ($source_inputs as $input) {
                $values = $service->getRelationValuesForRecord($relation, $databases_id, $input->id);
                $ids = $side === 'one' ? $values->pluck('many_record_id') : $values->pluck('one_record_id');
                $per_record->put($input->id, $ids->map(function ($id) use ($other_labels) {
                    return $other_labels->get($id, '#' . $id);
                })->values());
            }

            $relation_display->put($relation->id, (object) [
                'side' => $side,
                'name' => $side === 'one' ? $relation->one_relation_name : $relation->many_relation_name,
                'values' => $per_record,
            ]);
        }

        return $this->view('databaserelations', compact(
            'frame_id', 'database', 'database_frame', 'relations', 'source_inputs', 'source_labels', 'relation_display'
        ));
    }

    public function editRelations($request, $page_id, $frame_id, $id = null)
    {
        $request->flash();
        $databases = Databases::orderBy('databases_name')->orderBy('id')->get();
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $service = new DatabaseRelationService();
        $relations = $databases_id ? $service->getRelations($databases_id) : collect();
        $columns = DatabasesColumns::orderBy('databases_id')->orderBy('display_sequence')->orderBy('id')->get()->groupBy('databases_id');
        $database_frames = collect();
        foreach ($databases as $database) {
            $frames = DB::table('frames')
                ->leftJoin('pages', 'pages.id', '=', 'frames.page_id')
                ->where('frames.bucket_id', $database->bucket_id)
                ->select('frames.id', 'frames.page_id', 'pages.page_name')
                ->orderBy('frames.page_id')->orderBy('frames.id')->get();
            $database_frames->put($database->id, $frames);
        }

        $editing_relation = $id
            ? DatabasesRelations::where('id', $id)
                ->where(function ($query) use ($databases_id) {
                    $query->where('one_database_id', $databases_id)->orWhere('many_database_id', $databases_id);
                })->first()
            : new DatabasesRelations();

        if ($id && !$editing_relation) {
            return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
        }

        return $this->view('databaserelations_edit_relations', compact(
            'databases', 'databases_id', 'relations', 'columns', 'database_frames', 'editing_relation'
        ))->withInput($request->all);
    }

    public function saveFrameDatabase($request, $page_id, $frame_id)
    {
        $validator = Validator::make($request->all(), ['databases_id' => ['required', 'integer', 'exists:databases,id']]);
        $validator->setAttributeNames(['databases_id' => '使用するDB']);

        if ($validator->fails()) {
            return $this->editRelations($request, $page_id, $frame_id)->withErrors($validator);
        }

        FrameConfig::updateOrCreate(
            ['frame_id' => $frame_id, 'name' => 'databases_id'],
            ['value' => (string) $request->databases_id]
        );

        return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
    }

    public function saveRelation($request, $page_id, $frame_id, $id = null)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $validator = Validator::make($request->all(), [
            'one_database_id' => ['required', 'integer', 'exists:databases,id'],
            'many_database_id' => ['required', 'integer', 'exists:databases,id', 'different:one_database_id'],
            'one_relation_name' => ['required', 'string', 'max:255'],
            'many_relation_name' => ['required', 'string', 'max:255'],
            'one_display_column_id' => ['nullable', 'integer', 'exists:databases_columns,id'],
            'many_display_column_id' => ['nullable', 'integer', 'exists:databases_columns,id'],
            'one_detail_frame_id' => ['nullable', 'integer', 'exists:frames,id'],
            'many_detail_frame_id' => ['nullable', 'integer', 'exists:frames,id'],
            'display_sequence' => ['required', 'integer'],
        ]);
        $validator->setAttributeNames([
            'one_database_id' => '単数件側のDB', 'many_database_id' => '複数件側のDB',
            'one_relation_name' => '単数件側での表示名', 'many_relation_name' => '複数件側での表示名',
            'one_display_column_id' => '単数件側の表示項目', 'many_display_column_id' => '複数件側の表示項目',
            'one_detail_frame_id' => '単数件側の詳細表示先', 'many_detail_frame_id' => '複数件側の詳細表示先',
            'display_sequence' => '表示順',
        ]);
        $validator->after(function ($validator) use ($request, $databases_id) {
            if ((int) $request->one_database_id !== $databases_id && (int) $request->many_database_id !== $databases_id) {
                $validator->errors()->add('one_database_id', '単数件側のDBまたは複数件側のDBのどちらかに、このフレームで使用するDBを指定してください。');
            }
            foreach ([['one_display_column_id','one_database_id'], ['many_display_column_id','many_database_id']] as $pair) {
                if ($request->input($pair[0]) &&
                    !DatabasesColumns::where('id', $request->input($pair[0]))->where('databases_id', $request->input($pair[1]))->exists()) {
                    $validator->errors()->add($pair[0], '表示項目は対応するDBの項目から選択してください。');
                }
            }
            $service = new DatabaseRelationService();
            foreach ([['one_detail_frame_id','one_database_id'], ['many_detail_frame_id','many_database_id']] as $pair) {
                if ($request->input($pair[0])) {
                    $valid_frame_ids = $service->getDatabaseFrames((int) $request->input($pair[1]))->pluck('id');
                    if (!$valid_frame_ids->contains((int) $request->input($pair[0]))) {
                        $validator->errors()->add($pair[0], '詳細表示先は対応するDBを表示しているページから選択してください。');
                    }
                }
            }
        });

        if ($validator->fails()) {
            return $this->editRelations($request, $page_id, $frame_id, $id)->withErrors($validator);
        }

        $relation = $id
            ? DatabasesRelations::where('id', $id)->where(function ($query) use ($databases_id) {
                $query->where('one_database_id', $databases_id)->orWhere('many_database_id', $databases_id);
            })->first()
            : new DatabasesRelations();

        if (!$relation) {
            return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
        }

        $relation->fill($request->only([
            'one_database_id','many_database_id','one_relation_name','many_relation_name',
            'one_display_column_id','one_detail_frame_id','many_display_column_id','many_detail_frame_id','display_sequence',
        ]));
        $relation->save();

        return $this->editRelations($request, $page_id, $frame_id);
    }

    public function deleteRelation($request, $page_id, $frame_id, $id)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $relation = DatabasesRelations::where('id', $id)->where(function ($query) use ($databases_id) {
            $query->where('one_database_id', $databases_id)->orWhere('many_database_id', $databases_id);
        })->first();

        if ($relation) {
            DB::transaction(function () use ($relation) {
                DatabasesRelationValues::where('databases_relation_id', $relation->id)->delete();
                $relation->delete();
            });
        }

        return $this->editRelations($request, $page_id, $frame_id);
    }

    public function editRecordRelations($request, $page_id, $frame_id, $id)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $database = $databases_id ? Databases::find($databases_id) : null;
        $source_input = $databases_id
            ? DatabasesInputs::where('id', $id)->where('databases_id', $databases_id)->first()
            : null;

        if (!$database || !$source_input) {
            return redirect('/plugin/databaserelations/index/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
        }

        $service = new DatabaseRelationService();
        $service->cleanupOrphanValues($databases_id);
        $relations = $service->getRelations($databases_id);
        $relation_forms = collect();

        foreach ($relations as $relation) {
            $side = $relation->sideForDatabase($databases_id);
            $other_database_id = $side === 'one' ? $relation->many_database_id : $relation->one_database_id;
            $other_column_id = $side === 'one' ? $relation->many_display_column_id : $relation->one_display_column_id;
            $other_inputs = DatabasesInputs::where('databases_id', $other_database_id)->orderBy('display_sequence')->orderBy('id')->get();
            $options = $this->getRecordLabels(
                $other_inputs,
                $other_column_id ? DatabasesColumns::find($other_column_id) : $this->getDefaultDisplayColumn($other_database_id)
            );
            $values = $service->getRelationValuesForRecord($relation, $databases_id, $id);
            $selected = $side === 'one' ? $values->pluck('many_record_id')->all() : $values->pluck('one_record_id')->all();

            $relation_forms->put($relation->id, (object) [
                'side' => $side,
                'name' => $side === 'one' ? $relation->one_relation_name : $relation->many_relation_name,
                'options' => $options,
                'selected' => $selected,
            ]);
        }

        $source_label = $this->getRecordLabels(collect([$source_input]), $this->getDefaultDisplayColumn($databases_id))
            ->get($source_input->id, '#' . $source_input->id);

        return $this->view('databaserelations_edit_record', compact(
            'frame_id','database','source_input','source_label','relations','relation_forms'
        ));
    }

    public function saveRecordRelations($request, $page_id, $frame_id, $id)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $record = DatabasesInputs::where('id', $id)->where('databases_id', $databases_id)->first();
        if (!$record) {
            return redirect('/plugin/databaserelations/index/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
        }

        $service = new DatabaseRelationService();
        $relations = $service->getRelations($databases_id);
        $requested = (array) $request->input('relation_values', []);
        $errors = [];

        foreach ($relations as $relation) {
            $side = $relation->sideForDatabase($databases_id);
            $other_database_id = $side === 'one' ? $relation->many_database_id : $relation->one_database_id;
            $ids = $side === 'one'
                ? array_values(array_filter((array) ($requested[$relation->id] ?? [])))
                : array_values(array_filter([(int) ($requested[$relation->id] ?? 0)]));

            foreach ($ids as $other_id) {
                if (!DatabasesInputs::where('id', $other_id)->where('databases_id', $other_database_id)->exists()) {
                    $errors['relation_values.' . $relation->id] = '関連先レコードが正しくありません。';
                    break;
                }
            }
        }

        if ($errors) {
            return $this->editRecordRelations($request, $page_id, $frame_id, $id)->withErrors($errors);
        }

        DB::transaction(function () use ($relations, $requested, $databases_id, $id, $service) {
            foreach ($relations as $relation) {
                $side = $relation->sideForDatabase($databases_id);
                if ($side === 'one') {
                    $service->saveFromOneSide($relation->id, $id, (array) ($requested[$relation->id] ?? []));
                } else {
                    $service->saveFromManySide($relation->id, $id, (int) ($requested[$relation->id] ?? 0));
                }
            }
        });

        return redirect('/plugin/databaserelations/index/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
    }

    private function getDefaultDisplayColumn($databases_id)
    {
        if (!$databases_id) return null;
        return DatabasesColumns::where('databases_id', $databases_id)->where('title_flag', 1)
            ->orderBy('display_sequence')->orderBy('id')->first()
            ?: DatabasesColumns::where('databases_id', $databases_id)->orderBy('display_sequence')->orderBy('id')->first();
    }

    private function getRecordLabels($inputs, $display_column)
    {
        $labels = collect();
        if ($inputs->isEmpty()) return $labels;
        if (!$display_column) {
            foreach ($inputs as $input) $labels->put($input->id, '#' . $input->id);
            return $labels;
        }

        $values = DatabasesInputCols::whereIn('databases_inputs_id', $inputs->pluck('id'))
            ->where('databases_columns_id', $display_column->id)->pluck('value', 'databases_inputs_id');

        foreach ($inputs as $input) {
            $value = $values->get($input->id);
            if ($display_column->column_type == 'created') $value = $input->created_at;
            elseif ($display_column->column_type == 'updated') $value = $input->updated_at;
            elseif ($display_column->column_type == 'posted') $value = $input->posted_at;
            elseif ($display_column->column_type == 'display') $value = $input->display_sequence;
            $labels->put($input->id, strlen((string) $value) ? (string) $value : '#' . $input->id);
        }
        return $labels;
    }
}
