<?php

namespace App\Models\User\DatabaseRelations;

use Illuminate\Database\Eloquent\Model;
use App\Models\User\Databases\Databases;
use App\UserableNohistory;

class DatabasesRelations extends Model
{
    use UserableNohistory;

    const TARGET_TYPE_DATABASE = 'database';

    protected $table = 'databases_relations';

    protected $fillable = [
        'one_database_id',
        'many_database_id',
        'one_relation_name',
        'many_relation_name',
        'one_display_column_id',
        'one_detail_frame_id',
        'many_display_column_id',
        'many_detail_frame_id',
        'display_sequence',
    ];

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
