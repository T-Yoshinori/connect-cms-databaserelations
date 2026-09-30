<?php

namespace App\Plugins\User\Databaserelations\Services;

use App\Models\User\DatabaseRelations\DatabasesRelations;
use App\Models\User\DatabaseRelations\DatabasesRelationValues;
use App\Models\Common\Frame;
use App\Models\User\Databases\Databases;
use App\Models\User\Databases\DatabasesInputs;
use App\Models\User\Databases\DatabasesInputCols;
use App\Models\User\Databases\DatabasesColumns;
use App\Models\User\Databases\DatabasesColumnsSelects;
use App\Plugins\User\Databases\DatabasesTool;

class DatabaseRelationService
{
    public function getRelations($databases_id)
    {
        return DatabasesRelations::where(function ($query) use ($databases_id) {
                $query->where('one_database_id', $databases_id)
                    ->orWhere('many_database_id', $databases_id);
            })
            ->orderBy('display_sequence')
            ->orderBy('id')
            ->get();
    }

    public function getRelationValuesForRecord($relation, $databases_id, $record_id)
    {
        $side = $relation->sideForDatabase($databases_id);

        if ($side === 'one') {
            return DatabasesRelationValues::where('databases_relation_id', $relation->id)
                ->where('one_record_id', $record_id)
                ->orderBy('id')
                ->get();
        }

        if ($side === 'many') {
            return DatabasesRelationValues::where('databases_relation_id', $relation->id)
                ->where('many_record_id', $record_id)
                ->get();
        }

        return collect();
    }

    public function saveRelationValues($relation, $databases_id, $record_id, array $other_record_ids)
    {
        $side = $relation->sideForDatabase($databases_id);
        $other_record_ids = collect($other_record_ids)
            ->filter()
            ->map(function ($id) { return (int) $id; })
            ->unique()
            ->values();

        if ($relation->isManyToMany()) {
            return $this->saveManyToMany($relation->id, $side, $record_id, $other_record_ids);
        }

        if ($side === 'one') {
            return $this->saveFromOneSide($relation->id, $record_id, $other_record_ids->all());
        }

        return $this->saveFromManySide($relation->id, $record_id, (int) ($other_record_ids->first() ?: 0));
    }

    public function saveFromOneSide($relation_id, $one_record_id, array $many_record_ids)
    {
        $many_record_ids = collect($many_record_ids)
            ->filter()
            ->map(function ($id) { return (int) $id; })
            ->unique()
            ->values();

        DatabasesRelationValues::where('databases_relation_id', $relation_id)
            ->where('one_record_id', $one_record_id)
            ->whereNotIn('many_record_id', $many_record_ids)
            ->delete();

        foreach ($many_record_ids as $many_record_id) {
            // 1:N では多側レコードの所属先を1件に保つ。
            DatabasesRelationValues::where('databases_relation_id', $relation_id)
                ->where('many_record_id', $many_record_id)
                ->where('one_record_id', '<>', $one_record_id)
                ->delete();

            DatabasesRelationValues::updateOrCreate(
                [
                    'databases_relation_id' => $relation_id,
                    'one_record_id' => $one_record_id,
                    'many_record_id' => $many_record_id,
                ],
                []
            );
        }
    }

    public function saveFromManySide($relation_id, $many_record_id, $one_record_id)
    {
        DatabasesRelationValues::where('databases_relation_id', $relation_id)
            ->where('many_record_id', $many_record_id)
            ->delete();

        if (!$one_record_id) {
            return null;
        }

        return DatabasesRelationValues::create([
            'databases_relation_id' => $relation_id,
            'one_record_id' => $one_record_id,
            'many_record_id' => $many_record_id,
        ]);
    }

    private function saveManyToMany($relation_id, $side, $record_id, $other_record_ids)
    {
        $query = DatabasesRelationValues::where('databases_relation_id', $relation_id);

        if ($side === 'one') {
            $query->where('one_record_id', $record_id)
                ->whereNotIn('many_record_id', $other_record_ids)
                ->delete();

            foreach ($other_record_ids as $many_record_id) {
                DatabasesRelationValues::updateOrCreate(
                    [
                        'databases_relation_id' => $relation_id,
                        'one_record_id' => $record_id,
                        'many_record_id' => $many_record_id,
                    ],
                    []
                );
            }
            return;
        }

        $query->where('many_record_id', $record_id)
            ->whereNotIn('one_record_id', $other_record_ids)
            ->delete();

        foreach ($other_record_ids as $one_record_id) {
            DatabasesRelationValues::updateOrCreate(
                [
                    'databases_relation_id' => $relation_id,
                    'one_record_id' => $one_record_id,
                    'many_record_id' => $record_id,
                ],
                []
            );
        }
    }

    public function hasManyToManyConflicts($relation_id)
    {
        return DatabasesRelationValues::where('databases_relation_id', $relation_id)
            ->select('many_record_id')
            ->groupBy('many_record_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    public function cleanupOrphanValues($databases_id)
    {
        $relations = $this->getRelations($databases_id);

        foreach ($relations as $relation) {
            $one_ids = DatabasesInputs::where('databases_id', $relation->one_database_id)->pluck('id');
            $many_ids = DatabasesInputs::where('databases_id', $relation->many_database_id)->pluck('id');

            DatabasesRelationValues::where('databases_relation_id', $relation->id)
                ->whereNotIn('one_record_id', $one_ids)
                ->delete();

            DatabasesRelationValues::where('databases_relation_id', $relation->id)
                ->whereNotIn('many_record_id', $many_ids)
                ->delete();
        }
    }
    /**
     * Databases 詳細画面に表示する双方向の関連レコードを取得する。
     */
    public function getRelatedRecordsForDetail($databases_id, $record_id)
    {
        return $this->getRelations($databases_id)->map(function ($relation) use ($databases_id, $record_id) {
            $side = $relation->sideForDatabase($databases_id);
            $values = $this->getRelationValuesForRecord($relation, $databases_id, $record_id);

            $other_database_id = $side === 'one'
                ? $relation->many_database_id
                : $relation->one_database_id;
            $display_column_id = $side === 'one'
                ? $relation->many_display_column_id
                : $relation->one_display_column_id;
            $name = $side === 'one'
                ? $relation->one_relation_name
                : $relation->many_relation_name;

            $record_ids = $side === 'one'
                ? $values->pluck('many_record_id')
                : $values->pluck('one_record_id');

            $columns = DatabasesColumns::where('databases_id', $other_database_id)
                ->orderBy('display_sequence')
                ->orderBy('id')
                ->get();
            $hide_columns_ids = (new DatabasesTool())->getHideColumnsIds($columns, 'list_detail_display_flag');

            $search_key = 'relation_search_' . $relation->id;
            $sort_key = 'relation_sort_' . $relation->id;
            $filter_key_prefix = 'relation_filter_' . $relation->id . '_';
            $search_keyword = trim((string) request($search_key, ''));
            $sort_value = (string) request($sort_key, '');

            // 関連先DBで「絞り込み対象」とされた選択項目を、そのまま関連一覧でも利用する。
            $select_columns = $columns->where('select_flag', 1)
                ->whereIn('column_type', ['radio', 'checkbox', 'select'])
                ->whereNotIn('id', $hide_columns_ids)
                ->values();
            $columns_selects = DatabasesColumnsSelects::whereIn('databases_columns_id', $select_columns->pluck('id'))
                ->orderBy('databases_columns_id')
                ->orderBy('display_sequence')
                ->get();

            $filter_values = [];
            $search_columns = [];
            foreach ($select_columns as $select_column) {
                $filter_key = $filter_key_prefix . $select_column->id;
                $allowed_values = $columns_selects
                    ->where('databases_columns_id', $select_column->id)
                    ->pluck('value')
                    ->map(function ($value) { return (string) $value; })
                    ->all();
                $filter_value = collect((array) request($filter_key, []))
                    ->map(function ($value) { return (string) $value; })
                    ->filter(function ($value) use ($allowed_values) {
                        return strlen($value) && in_array($value, $allowed_values, true);
                    })
                    ->unique()
                    ->values()
                    ->all();
                $filter_values[$select_column->id] = $filter_value;

                if (!empty($filter_value)) {
                    $search_columns[] = [
                        'columns_id' => $select_column->id,
                        'value' => $filter_value,
                        'where' => $select_column->column_type === 'checkbox' ? 'PART' : 'ALL',
                    ];
                }
            }

            $inputs_query = DatabasesInputs::select('databases_inputs.*')
                ->where('databases_inputs.databases_id', $other_database_id)
                ->whereIn('databases_inputs.id', $record_ids);

            if (strlen($search_keyword)) {
                $inputs_query = DatabasesTool::appendSearchKeyword(
                    'databases_inputs.id',
                    $inputs_query,
                    $columns->pluck('id'),
                    $hide_columns_ids,
                    $search_keyword
                );
            }

            if (!empty($search_columns)) {
                $inputs_query = DatabasesTool::appendSearchColumnsMultiple(
                    'databases_inputs.id',
                    $inputs_query,
                    $search_columns
                );
            }

            $sort_columns = $columns->whereIn('sort_flag', [1, 2, 3])
                ->whereNotIn('id', $hide_columns_ids)
                ->values();
            $inputs_query = $this->applyRelatedSort($inputs_query, $sort_columns, $sort_value);

            $page_key = 'relation_page_' . $relation->id;
            $view_count = in_array((int) $relation->view_count, [5, 10, 20, 50, 100])
                ? (int) $relation->view_count
                : 10;
            $inputs = $inputs_query->paginate($view_count, ['databases_inputs.*'], $page_key);
            $inputs->appends(request()->query())->fragment('relation-' . $relation->id);

            $display_column = $display_column_id
                ? DatabasesColumns::find($display_column_id)
                : $this->getDefaultDisplayColumn($other_database_id);

            // 関連先DBの標準「一覧に表示する」設定をそのまま利用する。
            $list_columns = $columns->where('list_hide_flag', 0)
                ->whereNotIn('id', $hide_columns_ids)
                ->sortBy(function ($column) {
                    return sprintf(
                        '%08d-%08d-%08d-%08d',
                        $column->row_group,
                        $column->column_group,
                        $column->display_sequence,
                        $column->id
                    );
                })
                ->values();

            $detail_frame_id = $side === 'one' ? $relation->many_detail_frame_id : $relation->one_detail_frame_id;
            $detail_frame = $this->resolveDetailFrame($other_database_id, $detail_frame_id);
            $other_database = Databases::find($other_database_id);

            // Paginator 自体は保持し、現在ページのCollectionだけを表示用データへ変換する。
            // Paginator に map() すると Collection が返り、total()/links() 等が使えなくなるため。
            $inputs->setCollection(
                $inputs->getCollection()->map(function ($input) use ($display_column, $detail_frame, $list_columns) {
                    $column_values = $list_columns->mapWithKeys(function ($column) use ($input) {
                        return [$column->id => $this->getRecordColumnValue($input, $column)];
                    });

                    return (object) [
                        'input' => $input,
                        'label' => $this->getRecordLabel($input, $display_column),
                        'detail_frame' => $detail_frame,
                        'column_values' => $column_values,
                    ];
                })->values()
            );

            $items = $inputs;

            return (object) [
                'relation' => $relation,
                'side' => $side,
                'name' => $name,
                'database' => $other_database,
                'display_column' => $display_column,
                'list_columns' => $list_columns,
                'sort_columns' => $sort_columns,
                'select_columns' => $select_columns,
                'columns_selects' => $columns_selects,
                'filter_key_prefix' => $filter_key_prefix,
                'filter_values' => $filter_values,
                'search_key' => $search_key,
                'sort_key' => $sort_key,
                'page_key' => $page_key,
                'view_count' => $view_count,
                'search_keyword' => $search_keyword,
                'sort_value' => $sort_value,
                'items' => $items,
            ];
        });
    }

    /**
     * 関連一覧の並び順を適用する。
     * DatabasesFrames には依存せず、関連先DBの DatabasesColumns::sort_flag のみを利用する。
     */
    private function applyRelatedSort($query, $sort_columns, $sort_value)
    {
        if (!preg_match('/^(\\d+)_(asc|desc)$/', $sort_value, $matches)) {
            return $query->orderBy('databases_inputs.id', 'asc');
        }

        $column_id = (int) $matches[1];
        $order = $matches[2];
        $column = $sort_columns->firstWhere('id', $column_id);

        if (!$column ||
                ($order === 'asc' && !in_array((int) $column->sort_flag, [1, 2])) ||
                ($order === 'desc' && !in_array((int) $column->sort_flag, [1, 3]))) {
            return $query->orderBy('databases_inputs.id', 'asc');
        }

        if ($column->column_type === 'created') {
            $query->orderBy('databases_inputs.created_at', $order);
        } elseif ($column->column_type === 'updated') {
            $query->orderBy('databases_inputs.updated_at', $order);
        } elseif ($column->column_type === 'posted') {
            $query->orderBy('databases_inputs.posted_at', $order);
        } elseif ($column->column_type === 'display') {
            $query->orderBy('databases_inputs.display_sequence', $order);
        } elseif ($column->column_type === 'views') {
            $query->orderBy('databases_inputs.views', $order);
        } else {
            $query->leftJoin('databases_input_cols as relation_sort_col', function ($join) use ($column_id) {
                $join->on('relation_sort_col.databases_inputs_id', '=', 'databases_inputs.id')
                    ->where('relation_sort_col.databases_columns_id', '=', $column_id);
            });
            $query->orderBy('relation_sort_col.value', $order);
        }

        return $query->orderBy('databases_inputs.id', 'asc');
    }

    public function getDatabaseFrames($databases_id)
    {
        $database = Databases::find($databases_id);
        if (!$database) {
            return collect();
        }

        return Frame::where('bucket_id', $database->bucket_id)
            ->orderBy('page_id')
            ->orderBy('id')
            ->get();
    }

    public function resolveDatabaseFrameForRelations($databases_id, $relations)
    {
        $configured_ids = collect($relations)->map(function ($relation) use ($databases_id) {
            $side = $relation->sideForDatabase($databases_id);
            return $side === 'one' ? $relation->one_detail_frame_id : ($side === 'many' ? $relation->many_detail_frame_id : null);
        })->filter()->map(function ($id) {
            return (int) $id;
        })->unique()->values();

        if ($configured_ids->count() === 1) {
            return $this->resolveDetailFrame($databases_id, $configured_ids->first());
        }

        return $this->resolveDetailFrame($databases_id);
    }

    public function resolveDetailFrame($databases_id, $configured_frame_id = null)
    {
        $frames = $this->getDatabaseFrames($databases_id);

        if ($configured_frame_id) {
            $configured = $frames->firstWhere('id', (int) $configured_frame_id);
            if ($configured) {
                return $configured;
            }
        }

        return $frames->count() === 1 ? $frames->first() : null;
    }

    private function getDefaultDisplayColumn($databases_id)
    {
        return DatabasesColumns::where('databases_id', $databases_id)
            ->where('title_flag', 1)
            ->orderBy('display_sequence')
            ->orderBy('id')
            ->first()
            ?: DatabasesColumns::where('databases_id', $databases_id)
                ->orderBy('display_sequence')
                ->orderBy('id')
                ->first();
    }

    private function getRecordColumnValue($input, $column)
    {
        if ($column->column_type == 'created') {
            return (string) $input->created_at;
        }
        if ($column->column_type == 'updated') {
            return (string) $input->updated_at;
        }
        if ($column->column_type == 'posted') {
            return (string) $input->posted_at;
        }
        if ($column->column_type == 'display') {
            return (string) $input->display_sequence;
        }

        $value = DatabasesInputCols::where('databases_inputs_id', $input->id)
            ->where('databases_columns_id', $column->id)
            ->value('value');

        return is_null($value) ? '' : (string) $value;
    }

    private function getRecordLabel($input, $display_column)
    {
        if (!$display_column) {
            return '#' . $input->id;
        }

        if ($display_column->column_type == 'created') {
            $value = $input->created_at;
        } elseif ($display_column->column_type == 'updated') {
            $value = $input->updated_at;
        } elseif ($display_column->column_type == 'posted') {
            $value = $input->posted_at;
        } elseif ($display_column->column_type == 'display') {
            $value = $input->display_sequence;
        } else {
            $value = DatabasesInputCols::where('databases_inputs_id', $input->id)
                ->where('databases_columns_id', $display_column->id)
                ->value('value');
        }

        return strlen((string) $value) ? (string) $value : '#' . $input->id;
    }

}
