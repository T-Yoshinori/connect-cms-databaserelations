<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('databases_relations', function (Blueprint $table) {
            $table->string('relation_type', 32)
                ->default('one_to_many')
                ->after('many_database_id');
        });

        // 既存定義はすべて従来どおり 1:N として扱う。
        DB::table('databases_relations')->update(['relation_type' => 'one_to_many']);

        Schema::table('databases_relation_values', function (Blueprint $table) {
            $table->dropUnique('db_relation_values_relation_many_unique');
            $table->unique(
                ['databases_relation_id', 'one_record_id', 'many_record_id'],
                'db_relation_values_relation_pair_unique'
            );
        });
    }

    public function down()
    {
        // N:N のままでは旧 1:N の一意制約へ戻せないため、競合があれば明示的に停止する。
        $conflict = DB::table('databases_relation_values')
            ->select('databases_relation_id', 'many_record_id', DB::raw('COUNT(*) as relation_count'))
            ->groupBy('databases_relation_id', 'many_record_id')
            ->having('relation_count', '>', 1)
            ->first();

        if ($conflict) {
            throw new \RuntimeException(
                'N:N の関連付けが存在するため、DatabaseRelations の 1:N 構造へロールバックできません。'
            );
        }

        Schema::table('databases_relation_values', function (Blueprint $table) {
            $table->dropUnique('db_relation_values_relation_pair_unique');
            $table->unique(
                ['databases_relation_id', 'many_record_id'],
                'db_relation_values_relation_many_unique'
            );
        });

        Schema::table('databases_relations', function (Blueprint $table) {
            $table->dropColumn('relation_type');
        });
    }
};
