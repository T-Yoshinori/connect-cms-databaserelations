<?php

namespace App\Plugins\User\Databaserelations\Services;

use App\Models\User\DatabaseRelations\DatabasesRelations;
use App\Models\User\DatabaseRelations\DatabasesRelationValues;
use App\Models\Common\Frame;
use App\Models\User\Databases\Databases;
use App\Models\User\Databases\DatabasesInputs;
use App\Models\User\Databases\DatabasesInputCols;
use App\Models\User\Databases\DatabasesColumns;

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
            // 多側レコードは同一リレーション内で1側レコードを1件だけ持つ。
            DatabasesRelationValues::updateOrCreate(
                [
                    'databases_relation_id' => $relation_id,
                    'many_record_id' => $many_record_id,
                ],
                [
                    'one_record_id' => $one_record_id,
                ]
            );
        }
    }

    public function saveFromManySide($relation_id, $many_record_id, $one_record_id)
    {
        if (!$one_record_id) {
            return DatabasesRelationValues::where('databases_relation_id', $relation_id)
                ->where('many_record_id', $many_record_id)
                ->delete();
        }

        return DatabasesRelationValues::updateOrCreate(
            [
                'databases_relation_id' => $relation_id,
                'many_record_id' => $many_record_id,
            ],
            [
                'one_record_id' => $one_record_id,
            ]
        );
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

            $inputs = DatabasesInputs::where('databases_id', $other_database_id)
                ->whereIn('id', $record_ids)
                ->get()
                ->keyBy('id');

            $display_column = $display_column_id
                ? DatabasesColumns::find($display_column_id)
                : $this->getDefaultDisplayColumn($other_database_id);

            $detail_frame_id = $side === 'one' ? $relation->many_detail_frame_id : $relation->one_detail_frame_id;
            $detail_frame = $this->resolveDetailFrame($other_database_id, $detail_frame_id);

            $items = $record_ids->map(function ($id) use ($inputs, $display_column, $detail_frame) {
                $input = $inputs->get($id);
                if (!$input) {
                    return null;
                }

                return (object) [
                    'input' => $input,
                    'label' => $this->getRecordLabel($input, $display_column),
                    'detail_frame' => $detail_frame,
                ];
            })->filter()->values();

            return (object) [
                'relation' => $relation,
                'side' => $side,
                'name' => $name,
                'items' => $items,
            ];
        });
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
