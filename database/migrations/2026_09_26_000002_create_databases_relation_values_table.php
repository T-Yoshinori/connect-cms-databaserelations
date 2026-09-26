<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDatabasesRelationValuesTable extends Migration
{
    public function up()
    {
        Schema::create('databases_relation_values', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('databases_relation_id');
            $table->integer('one_record_id');
            $table->integer('many_record_id');
            $table->integer('created_id')->nullable();
            $table->string('created_name', 255)->nullable();
            $table->integer('updated_id')->nullable();
            $table->string('updated_name', 255)->nullable();
            $table->timestamps();

            $table->index('databases_relation_id');
            $table->index('one_record_id', 'db_relation_values_one_record_index');
            $table->index('many_record_id', 'db_relation_values_many_record_index');
            $table->unique(['databases_relation_id', 'many_record_id'], 'db_relation_values_relation_many_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('databases_relation_values');
    }
}
