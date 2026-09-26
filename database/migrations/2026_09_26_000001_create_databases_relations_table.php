<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDatabasesRelationsTable extends Migration
{
    public function up()
    {
        Schema::create('databases_relations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('one_database_id');
            $table->integer('many_database_id');
            $table->string('one_relation_name', 255);
            $table->string('many_relation_name', 255);
            $table->integer('one_display_column_id')->nullable();
            $table->integer('one_detail_frame_id')->nullable();
            $table->integer('many_display_column_id')->nullable();
            $table->integer('many_detail_frame_id')->nullable();
            $table->integer('display_sequence')->default(0);
            $table->integer('created_id')->nullable();
            $table->string('created_name', 255)->nullable();
            $table->integer('updated_id')->nullable();
            $table->string('updated_name', 255)->nullable();
            $table->timestamps();

            $table->index('one_database_id', 'db_relations_one_database_index');
            $table->index('many_database_id', 'db_relations_many_database_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('databases_relations');
    }
}
