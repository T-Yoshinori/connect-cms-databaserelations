<?php

namespace App\Plugins\User\Databaserelations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User\DatabaseRelations\DatabasesRelations;
use App\Models\User\DatabaseRelations\DatabasesRelationValues;
use App\Models\User\DatabaseRelations\DatabasesEntityRelations;
use App\Models\User\DatabaseRelations\DatabasesEntityRelationValues;
use App\Models\Common\Group;
use App\User;
use App\Enums\UserStatus;
use App\Models\User\Databases\Databases;
use App\Models\User\Databases\DatabasesColumns;
use App\Models\User\Databases\DatabasesInputCols;
use App\Models\User\Databases\DatabasesInputs;
use App\Models\Core\FrameConfig;
use App\Plugins\User\Databaserelations\Services\DatabaseRelationService;
use App\Plugins\User\Databaserelations\Services\DatabaseEntityRelationService;
use App\Plugins\User\UserPluginBase;

class DatabaserelationsPlugin extends UserPluginBase
{
    public $use_getpost = false;

    public function getPublicFunctions()
    {
        return [
            'get' => ['index', 'editRelations', 'editRecordRelations'],
            'post' => ['saveFrameDatabase', 'saveRelation', 'deleteRelation', 'saveEntityRelation', 'saveGroupEntityRelation', 'deleteEntityRelation', 'saveRecordRelations'],
        ];
    }

    public function declareRole()
    {
        return [
            'editRelations' => ['frames.edit'],
            'saveFrameDatabase' => ['frames.edit'],
            'saveRelation' => ['frames.edit'],
            'deleteRelation' => ['frames.delete'],
            'saveEntityRelation' => ['frames.edit'],
            'saveGroupEntityRelation' => ['frames.edit'],
            'deleteEntityRelation' => ['frames.delete'],
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
        $entity_service = new DatabaseEntityRelationService();
        $entity_relations = $databases_id ? $entity_service->getRelations($databases_id) : collect();
        $database_frame = $databases_id ? $service->resolveDatabaseFrameForRelations($databases_id, $relations) : null;

        if ($databases_id) {
            $service->cleanupOrphanValues($databases_id);
            $entity_service->cleanupOrphanValues($databases_id);
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

        $entity_display = collect();
        foreach ($entity_relations as $entity_relation) {
            $per_record = collect();
            foreach ($source_inputs as $input) {
                $values = $entity_service->getValues($entity_relation->id, $input->id);
                $labels = collect();
                if ($entity_relation->target_type === DatabasesEntityRelations::TARGET_TYPE_USER) {
                    foreach ($values as $value) {
                        $user = User::find($value->target_id);
                        if ($user) {
                            $label = $user->name . (strlen((string) $user->userid) ? '（' . $user->userid . '）' : '');
                            if ((int) $user->status !== UserStatus::active) {
                                $label .= '［利用停止等］';
                            }
                            $labels->push($label);
                        }
                    }
                } elseif ($entity_relation->target_type === DatabasesEntityRelations::TARGET_TYPE_GROUP) {
                    foreach ($values as $value) {
                        $group = Group::find($value->target_id);
                        if ($group) {
                            $labels->push($group->name);
                        }
                    }
                }
                $per_record->put($input->id, $labels);
            }
            $entity_display->put($entity_relation->id, (object) [
                'name' => $entity_relation->relation_name,
                'values' => $per_record,
            ]);
        }

        return $this->view('databaserelations', compact(
            'frame_id', 'database', 'database_frame', 'relations', 'source_inputs', 'source_labels', 'relation_display',
            'entity_relations', 'entity_display'
        ));
    }

    public function editRelations($request, $page_id, $frame_id, $id = null)
    {
        $request->flash();
        $databases = Databases::orderBy('databases_name')->orderBy('id')->get();
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $service = new DatabaseRelationService();
        $relations = $databases_id ? $service->getRelations($databases_id) : collect();
        $entity_relations = $databases_id ? (new DatabaseEntityRelationService())->getRelations($databases_id) : collect();
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
            'databases', 'databases_id', 'relations', 'entity_relations', 'columns', 'database_frames', 'editing_relation'
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
            'relation_type' => ['required', 'in:one_to_many,many_to_many'],
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
            'one_database_id' => '関連先DB', 'many_database_id' => '対象DB', 'relation_type' => '関連付け方',
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

        $service = new DatabaseRelationService();
        if ($relation->id &&
            $relation->isManyToMany() &&
            $request->relation_type === DatabasesRelations::RELATION_TYPE_ONE_TO_MANY &&
            $service->hasManyToManyConflicts($relation->id)) {
            $validator->errors()->add(
                'relation_type',
                '同じ対象DBレコードに複数の関連先が設定されているため、1件対複数件には変更できません。重複する関連付けを解消してから変更してください。'
            );
            return $this->editRelations($request, $page_id, $frame_id, $id)->withErrors($validator);
        }

        $relation->fill($request->only([
            'one_database_id','many_database_id','relation_type','one_relation_name','many_relation_name',
            'one_display_column_id','one_detail_frame_id','many_display_column_id','many_detail_frame_id','display_sequence',
        ]));
        $relation->save();

        return $this->editRelations($request, $page_id, $frame_id);
    }

    public function saveEntityRelation($request, $page_id, $frame_id)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        if (!$databases_id || !Databases::where('id', $databases_id)->exists()) {
            return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
        }

        $validator = Validator::make($request->all(), [
            'relation_name' => ['required', 'string', 'max:255'],
            'display_sequence' => ['required', 'integer'],
        ]);
        $validator->setAttributeNames(['relation_name' => '表示名', 'display_sequence' => '表示順']);
        if ($validator->fails()) {
            return $this->editRelations($request, $page_id, $frame_id)->withErrors($validator, 'entityRelation');
        }

        if (DatabasesEntityRelations::where('databases_id', $databases_id)
            ->where('target_type', DatabasesEntityRelations::TARGET_TYPE_USER)->exists()) {
            $validator->errors()->add('relation_name', 'Connect-CMSユーザーとの関連は、このDBにすでに設定されています。');
            return $this->editRelations($request, $page_id, $frame_id)->withErrors($validator, 'entityRelation');
        }

        DatabasesEntityRelations::create([
            'databases_id' => $databases_id,
            'target_type' => DatabasesEntityRelations::TARGET_TYPE_USER,
            'relation_name' => $request->relation_name,
            'required_flag' => false,
            'target_max_count' => 1,
            'target_unique' => true,
            'display_sequence' => (int) $request->display_sequence,
        ]);

        return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
    }

    public function saveGroupEntityRelation($request, $page_id, $frame_id)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        if (!$databases_id || !Databases::where('id', $databases_id)->exists()) {
            return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
        }

        $validator = Validator::make($request->all(), [
            'group_relation_name' => ['required', 'string', 'max:255'],
            'group_display_sequence' => ['required', 'integer'],
        ]);
        $validator->setAttributeNames(['group_relation_name' => '表示名', 'group_display_sequence' => '表示順']);
        if ($validator->fails()) {
            return $this->editRelations($request, $page_id, $frame_id)
                ->withErrors($validator, 'groupEntityRelation');
        }

        if (DatabasesEntityRelations::where('databases_id', $databases_id)
            ->where('target_type', DatabasesEntityRelations::TARGET_TYPE_GROUP)->exists()) {
            $validator->errors()->add('group_relation_name', 'Connect-CMSユーザーグループとの関連は、このDBにすでに設定されています。');
            return $this->editRelations($request, $page_id, $frame_id)
                ->withErrors($validator, 'groupEntityRelation');
        }

        DatabasesEntityRelations::create([
            'databases_id' => $databases_id,
            'target_type' => DatabasesEntityRelations::TARGET_TYPE_GROUP,
            'relation_name' => $request->group_relation_name,
            'required_flag' => false,
            'target_max_count' => 0,
            'target_unique' => false,
            'display_sequence' => (int) $request->group_display_sequence,
        ]);

        return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
    }

    public function deleteEntityRelation($request, $page_id, $frame_id, $id)
    {
        $databases_id = (int) FrameConfig::getConfigValue($this->frame_configs, 'databases_id', 0);
        $relation = DatabasesEntityRelations::where('id', $id)->where('databases_id', $databases_id)->first();
        if ($relation) {
            DB::transaction(function () use ($relation) {
                DatabasesEntityRelationValues::where('databases_entity_relation_id', $relation->id)->delete();
                $relation->delete();
            });
        }
        return redirect('/plugin/databaserelations/editRelations/' . $page_id . '/' . $frame_id . '#frame-' . $frame_id);
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
                'multiple' => $relation->isManyToMany() || $side === 'one',
            ]);
        }

        $entity_service = new DatabaseEntityRelationService();
        $entity_relations = $entity_service->getRelations($databases_id);
        $user_options = $entity_service->getUserOptions();
        $group_options = $entity_service->getGroupOptions();
        $entity_selected = collect();
        foreach ($entity_relations as $entity_relation) {
            $values = $entity_service->getValues($entity_relation->id, $id);
            $entity_selected->put($entity_relation->id, $values->pluck('target_id')->all());
        }

        $source_label = $this->getRecordLabels(collect([$source_input]), $this->getDefaultDisplayColumn($databases_id))
            ->get($source_input->id, '#' . $source_input->id);

        return $this->view('databaserelations_edit_record', compact(
            'frame_id','database','source_input','source_label','relations','relation_forms',
            'entity_relations','user_options','group_options','entity_selected'
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
            $multiple = $relation->isManyToMany() || $side === 'one';
            $ids = $multiple
                ? array_values(array_filter((array) ($requested[$relation->id] ?? [])))
                : array_values(array_filter([(int) ($requested[$relation->id] ?? 0)]));

            foreach ($ids as $other_id) {
                if (!DatabasesInputs::where('id', $other_id)->where('databases_id', $other_database_id)->exists()) {
                    $errors['relation_values.' . $relation->id] = '関連先レコードが正しくありません。';
                    break;
                }
            }
        }

        $entity_service = new DatabaseEntityRelationService();
        $entity_relations = $entity_service->getRelations($databases_id);
        $entity_requested = (array) $request->input('entity_relation_values', []);
        foreach ($entity_relations as $entity_relation) {
            $target_ids = array_values(array_filter((array) ($entity_requested[$entity_relation->id] ?? [])));
            if ($entity_relation->target_max_count > 0 && count(array_unique($target_ids)) > $entity_relation->target_max_count) {
                $errors['entity_relation_values.' . $entity_relation->id] = '関連付け可能な件数を超えています。';
                continue;
            }

            if ($entity_relation->target_type === DatabasesEntityRelations::TARGET_TYPE_USER) {
                foreach ($target_ids as $user_id) {
                    $user_id = (int) $user_id;
                    $selected_user = User::find($user_id);
                    $current_ids = $entity_service->getValues($entity_relation->id, $id)->pluck('target_id');
                    $is_existing_value = $current_ids->contains($user_id);
                    if (!$selected_user || ((int) $selected_user->status !== UserStatus::active && !$is_existing_value)) {
                        $errors['entity_relation_values.' . $entity_relation->id] = '新しく関連付ける場合は、利用可能なユーザーを選択してください。';
                        break;
                    }
                    if ($entity_relation->target_unique &&
                        DatabasesEntityRelationValues::where('databases_entity_relation_id', $entity_relation->id)
                            ->where('target_id', $user_id)->where('databases_input_id', '<>', $id)->exists()) {
                        $errors['entity_relation_values.' . $entity_relation->id] = 'このユーザーは、すでに別のレコードへ関連付けられています。';
                        break;
                    }
                }
            } elseif ($entity_relation->target_type === DatabasesEntityRelations::TARGET_TYPE_GROUP) {
                foreach ($target_ids as $group_id) {
                    if (!Group::where('id', (int) $group_id)->exists()) {
                        $errors['entity_relation_values.' . $entity_relation->id] = '関連先のユーザーグループが正しくありません。';
                        break;
                    }
                }
            }
        }

        if ($errors) {
            return $this->editRecordRelations($request, $page_id, $frame_id, $id)->withErrors($errors);
        }

        DB::transaction(function () use ($relations, $requested, $databases_id, $id, $service, $entity_relations, $entity_requested, $entity_service) {
            foreach ($relations as $relation) {
                $side = $relation->sideForDatabase($databases_id);
                $multiple = $relation->isManyToMany() || $side === 'one';
                $ids = $multiple
                    ? (array) ($requested[$relation->id] ?? [])
                    : [(int) ($requested[$relation->id] ?? 0)];
                $service->saveRelationValues($relation, $databases_id, $id, $ids);
            }
            foreach ($entity_relations as $entity_relation) {
                $target_ids = (array) ($entity_requested[$entity_relation->id] ?? []);
                if ($entity_relation->target_type === DatabasesEntityRelations::TARGET_TYPE_USER) {
                    $entity_service->saveUser($entity_relation, $id, (int) ($target_ids[0] ?? 0));
                } elseif ($entity_relation->target_type === DatabasesEntityRelations::TARGET_TYPE_GROUP) {
                    $entity_service->saveGroups($entity_relation, $id, $target_ids);
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
