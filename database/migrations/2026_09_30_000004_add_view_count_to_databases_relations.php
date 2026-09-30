<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('databases_relations', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(10)->after('display_sequence');
        });
    }

    public function down()
    {
        Schema::table('databases_relations', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
