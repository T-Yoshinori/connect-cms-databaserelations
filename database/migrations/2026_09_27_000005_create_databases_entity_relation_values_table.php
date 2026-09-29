<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDatabasesEntityRelationValuesTable extends Migration
{
    public function up()
    {
        Schema::create('databases_entity_relation_values', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('databases_entity_relation_id');
            $table->integer('databases_input_id');
            $table->integer('target_id');
            $table->integer('created_id')->nullable();
            $table->string('created_name', 255)->nullable();
            $table->integer('updated_id')->nullable();
            $table->string('updated_name', 255)->nullable();
            $table->timestamps();

            $table->index('databases_entity_relation_id', 'db_entity_values_relation_index');
            $table->index('databases_input_id', 'db_entity_values_input_index');
            $table->index('target_id', 'db_entity_values_target_index');
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

    public function down()
    {
        Schema::dropIfExists('databases_entity_relation_values');
    }
}
