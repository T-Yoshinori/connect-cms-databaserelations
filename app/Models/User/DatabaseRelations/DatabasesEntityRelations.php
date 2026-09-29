<?php

namespace App\Models\User\DatabaseRelations;

use Illuminate\Database\Eloquent\Model;
use App\UserableNohistory;

class DatabasesEntityRelations extends Model
{
    use UserableNohistory;

    const TARGET_TYPE_USER = 'user';
    const TARGET_TYPE_GROUP = 'group';

    protected $table = 'databases_entity_relations';

    protected $fillable = [
        'databases_id',
        'target_type',
        'relation_name',
        'required_flag',
        'target_max_count',
        'target_unique',
        'display_sequence',
    ];

    protected $casts = [
        'required_flag' => 'boolean',
        'target_unique' => 'boolean',
    ];

    public function values()
    {
        return $this->hasMany(DatabasesEntityRelationValues::class, 'databases_entity_relation_id');
    }
}
