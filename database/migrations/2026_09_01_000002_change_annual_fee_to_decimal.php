<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 修复:annual_fee 原为 unsignedInteger,¥199.50 会被截断为 199。
 * 改为 decimal(10,2) 保留小数,与 WeChatPayService 的 bcmul(*100) 转分对齐。
 *
 * 注意:SQLite 不原生支持 ALTER COLUMN 改类型,Laravel 在 SQLite 下用整表重建模拟,
 * 迁移可正常执行;若本地 SQLite 报错,手动删库重跑 migrate 即可。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->decimal('annual_fee', 10, 2)->unsigned()->change();
        });
    }

    public function down(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->unsignedInteger('annual_fee')->change();
        });
    }
};
