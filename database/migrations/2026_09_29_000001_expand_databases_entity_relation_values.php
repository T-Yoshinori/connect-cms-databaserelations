<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('databases_entity_relation_values', function (Blueprint $table) {
            $table->dropUnique('db_entity_values_relation_input_unique');
            $table->dropUnique('db_entity_values_relation_target_unique');
            $table->unique(
                ['databases_entity_relation_id', 'databases_input_id', 'target_id'],
                'db_entity_values_relation_input_target_unique'
            );
        });
    }

    public function down()
    {
        $multiple_targets = DB::table('databases_entity_relation_values')
            ->select('databases_entity_relation_id', 'databases_input_id')
            ->groupBy('databases_entity_relation_id', 'databases_input_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $reused_targets = DB::table('databases_entity_relation_values')
            ->select('databases_entity_relation_id', 'target_id')
            ->groupBy('databases_entity_relation_id', 'target_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($multiple_targets || $reused_targets) {
            throw new RuntimeException(
                'Entity relation values contain N:N data and cannot be rolled back without data loss.'
            );
        }

        Schema::table('databases_entity_relation_values', function (Blueprint $table) {
            $table->dropUnique('db_entity_values_relation_input_target_unique');
            $table->unique(
                ['databases_entity_relation_id', 'databases_input_id'],
                'db_entity_values_relation_input_unique'
            );
            $table->unique(
                ['databases_entity_relation_id', 'target_id'],
                'db_entity_values_relation_target_unique'
            );
        });
    }
};
