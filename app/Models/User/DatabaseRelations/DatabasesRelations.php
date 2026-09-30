<?php

namespace App\Models\User\DatabaseRelations;

use Illuminate\Database\Eloquent\Model;
use App\Models\User\Databases\Databases;
use App\UserableNohistory;

class DatabasesRelations extends Model
{
    use UserableNohistory;

    const TARGET_TYPE_DATABASE = 'database';
    const RELATION_TYPE_ONE_TO_MANY = 'one_to_many';
    const RELATION_TYPE_MANY_TO_MANY = 'many_to_many';

    protected $table = 'databases_relations';

    protected $fillable = [
        'one_database_id',
        'many_database_id',
        'relation_type',
        'one_relation_name',
        'many_relation_name',
        'one_display_column_id',
        'one_detail_frame_id',
        'many_display_column_id',
        'many_detail_frame_id',
        'display_sequence',
        'view_count',
    ];

    public function isManyToMany()
    {
        return $this->relation_type === self::RELATION_TYPE_MANY_TO_MANY;
    }

    public function oneDatabase()
    {
        return $this->belongsTo(Databases::class, 'one_database_id');
    }

    public function manyDatabase()
    {
        return $this->belongsTo(Databases::class, 'many_database_id');
    }

    public function values()
    {
        return $this->hasMany(DatabasesRelationValues::class, 'databases_relation_id');
    }

    public function sideForDatabase($databases_id)
    {
        if ((int) $this->one_database_id === (int) $databases_id) {
            return 'one';
        }
        if ((int) $this->many_database_id === (int) $databases_id) {
            return 'many';
        }
        return null;
    }
}
