<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDatabasesEntityRelationsTable extends Migration
{
    public function up()
    {
        Schema::create('databases_entity_relations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('databases_id');
            $table->string('target_type', 32);
            $table->string('relation_name', 255);
            $table->boolean('required_flag')->default(false);
            $table->unsignedInteger('target_max_count')->default(1);
            $table->boolean('target_unique')->default(true);
            $table->integer('display_sequence')->default(0);
            $table->integer('created_id')->nullable();
            $table->string('created_name', 255)->nullable();
            $table->integer('updated_id')->nullable();
            $table->string('updated_name', 255)->nullable();
            $table->timestamps();

            $table->index(['databases_id', 'target_type'], 'db_entity_relations_db_type_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('databases_entity_relations');
    }
}
