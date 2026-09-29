<?php

namespace App\Plugins\User\Databaserelations\Services;

use App\User;
use App\Enums\UserStatus;
use App\Models\Common\Group;
use App\Models\User\Databases\DatabasesInputs;
use App\Models\User\DatabaseRelations\DatabasesEntityRelations;
use App\Models\User\DatabaseRelations\DatabasesEntityRelationValues;

class DatabaseEntityRelationService
{
    public function getRelations($databases_id)
    {
        return DatabasesEntityRelations::where('databases_id', $databases_id)
            ->orderBy('display_sequence')->orderBy('id')->get();
    }

    public function getUserOptions()
    {
        return User::where('status', '<>', UserStatus::temporary_delete)
            ->orderBy('name')->orderBy('userid')->orderBy('id')->get();
    }

    public function getGroupOptions()
    {
        return Group::orderBy('display_sequence')->orderBy('name')->orderBy('id')->get();
    }

    public function getValues($relation_id, $input_id)
    {
        return DatabasesEntityRelationValues::where('databases_entity_relation_id', $relation_id)
            ->where('databases_input_id', $input_id)
            ->orderBy('id')
            ->get();
    }

    public function getValue($relation_id, $input_id)
    {
        return $this->getValues($relation_id, $input_id)->first();
    }

    public function saveTargets($relation, $input_id, array $target_ids)
    {
        $target_ids = collect($target_ids)
            ->filter()
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values();

        if ($relation->target_max_count > 0 && $target_ids->count() > $relation->target_max_count) {
            throw new \InvalidArgumentException('関連付け可能な件数を超えています。');
        }

        if ($relation->target_unique) {
            foreach ($target_ids as $target_id) {
                $used_by_other_record = DatabasesEntityRelationValues::where(
                    'databases_entity_relation_id',
                    $relation->id
                )->where('target_id', $target_id)
                    ->where('databases_input_id', '<>', $input_id)
                    ->exists();
                if ($used_by_other_record) {
                    throw new \InvalidArgumentException('同じ関連先を別のレコードへ重複して関連付けることはできません。');
                }
            }
        }

        DatabasesEntityRelationValues::where('databases_entity_relation_id', $relation->id)
            ->where('databases_input_id', $input_id)
            ->whereNotIn('target_id', $target_ids)
            ->delete();

        foreach ($target_ids as $target_id) {
            DatabasesEntityRelationValues::updateOrCreate([
                'databases_entity_relation_id' => $relation->id,
                'databases_input_id' => $input_id,
                'target_id' => $target_id,
            ], []);
        }
    }

    public function saveUser($relation, $input_id, $user_id)
    {
        $this->saveTargets($relation, $input_id, $user_id ? [(int) $user_id] : []);
    }

    public function saveGroups($relation, $input_id, array $group_ids)
    {
        $this->saveTargets($relation, $input_id, $group_ids);
    }

    public function cleanupOrphanValues($databases_id)
    {
        $relation_ids = DatabasesEntityRelations::where('databases_id', $databases_id)->pluck('id');
        if ($relation_ids->isEmpty()) {
            return;
        }

        DatabasesEntityRelationValues::whereIn('databases_entity_relation_id', $relation_ids)
            ->whereNotIn('databases_input_id', DatabasesInputs::where('databases_id', $databases_id)->select('id'))
            ->delete();

        $user_relation_ids = DatabasesEntityRelations::where('databases_id', $databases_id)
            ->where('target_type', DatabasesEntityRelations::TARGET_TYPE_USER)->pluck('id');
        if ($user_relation_ids->isNotEmpty()) {
            DatabasesEntityRelationValues::whereIn('databases_entity_relation_id', $user_relation_ids)
                ->whereNotIn('target_id', User::select('id'))->delete();
        }

        $group_relation_ids = DatabasesEntityRelations::where('databases_id', $databases_id)
            ->where('target_type', DatabasesEntityRelations::TARGET_TYPE_GROUP)->pluck('id');
        if ($group_relation_ids->isNotEmpty()) {
            DatabasesEntityRelationValues::whereIn('databases_entity_relation_id', $group_relation_ids)
                ->whereNotIn('target_id', Group::select('id'))->delete();
        }
    }
}
