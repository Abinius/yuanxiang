<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0 修复:同一田块本季最多一张有效认养单,防止并发下单超卖。
 * 配合 AdoptionService::createOrder 内 DB::transaction 使用;
 * 即使两个请求同时通过 exists() 预检,INSERT 阶段也会因唯一约束失败,
 * 被事务回滚并降级为 422,保证不出现两张 active 单。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->unique(['adoptable_type', 'adoptable_id', 'season_year']);
        });
    }

    public function down(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->dropUnique(['adoptable_type', 'adoptable_id', 'season_year']);
        });
    }
};
