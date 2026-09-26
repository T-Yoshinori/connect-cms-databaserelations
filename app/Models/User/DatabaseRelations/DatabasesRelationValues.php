<?php

namespace App\Models\User\DatabaseRelations;

use Illuminate\Database\Eloquent\Model;
use App\Models\User\Databases\DatabasesInputs;
use App\UserableNohistory;

class DatabasesRelationValues extends Model
{
    use UserableNohistory;

    protected $table = 'databases_relation_values';

    protected $fillable = [
        'databases_relation_id',
        'one_record_id',
        'many_record_id',
    ];

    public function relation()
    {
        return $this->belongsTo(DatabasesRelations::class, 'databases_relation_id');
    }

    public function oneRecord()
    {
        return $this->belongsTo(DatabasesInputs::class, 'one_record_id');
    }

    public function manyRecord()
    {
        return $this->belongsTo(DatabasesInputs::class, 'many_record_id');
    }
}
