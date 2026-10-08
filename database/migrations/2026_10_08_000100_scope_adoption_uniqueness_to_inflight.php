<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修「取消/退款的单永久占死地块+季」：原唯一索引不含 status，
 * 而应用层判重只数在约状态（pending_payment/pending_agreement/active），
 * 两层口径不一致 —— 弃付或退款后地块虽被释放回 available，再下单却会撞唯一键
 * 报「该田块本季已被认养」，且后台没有删除认养单的入口，该地块整季卖不出去。
 *
 * 改成部分唯一索引：只有「在约」行参与唯一约束，取消/到期的行不再占位。
 * 同时给两层并发兜底补上唯一索引（佣金同单只记一次、同次采收同认养只打一次单）。
 *
 * 注意：部分唯一索引需 SQLite 3.8+ / MySQL 8.0.13+ / PostgreSQL；
 * MariaDB 不支持索引 WHERE 子句，会在此抛错并回滚。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->dropUnique(['adoptable_type', 'adoptable_id', 'season_year']);
        });

        $index = 'uniq_adoptable_season_inflight';
        $cols = 'adoptable_type, adoptable_id, season_year';
        $predicate = "status NOT IN ('cancelled', 'ended')";

        match (DB::connection()->getDriverName()) {
            'sqlite', 'pgsql' => DB::statement(
                "CREATE UNIQUE INDEX {$index} ON adoptions ({$cols}) WHERE {$predicate}"
            ),
            'mysql' => DB::statement(
                "ALTER TABLE adoptions ADD UNIQUE INDEX {$index} ({$cols}) WHERE {$predicate}"
            ),
            default => throw new RuntimeException(
                '当前数据库驱动不支持部分唯一索引；若为 MariaDB，请改用「有效行标记列 + 该列唯一」方案。'
            ),
        };

        // 佣金流水：同一认养只应有一条（credit() 的幂等此前仅靠应用层 check-then-insert）
        Schema::table('commission_ledger', function (Blueprint $table) {
            $table->unique('adoption_id', 'uniq_commission_per_adoption');
        });

        // 配送单：同一次采收对同一认养只应打一次单（harvest_id 为空的手工单不受限）
        DB::statement(
            'CREATE UNIQUE INDEX uniq_delivery_per_harvest ON deliveries (adoption_id, harvest_id) WHERE harvest_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uniq_delivery_per_harvest');

        Schema::table('commission_ledger', function (Blueprint $table) {
            $table->dropUnique('uniq_commission_per_adoption');
        });

        DB::statement('DROP INDEX IF EXISTS uniq_adoptable_season_inflight');

        Schema::table('adoptions', function (Blueprint $table) {
            $table->unique(['adoptable_type', 'adoptable_id', 'season_year']);
        });
    }
};
