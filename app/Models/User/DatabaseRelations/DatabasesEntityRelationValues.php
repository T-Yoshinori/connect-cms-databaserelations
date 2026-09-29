<?php

namespace App\Models\User\DatabaseRelations;

use Illuminate\Database\Eloquent\Model;
use App\UserableNohistory;

class DatabasesEntityRelationValues extends Model
{
    use UserableNohistory;

    protected $table = 'databases_entity_relation_values';

    protected $fillable = [
        'databases_entity_relation_id',
        'databases_input_id',
        'target_id',
    ];

    public function relation()
    {
        return $this->belongsTo(DatabasesEntityRelations::class, 'databases_entity_relation_id');
    }
}
