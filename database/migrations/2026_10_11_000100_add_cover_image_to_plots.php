<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 地块封面图（R3 卡片竖版化）：认养列表 / 拼团田 / 我的田的 3:4 卡片封面。
 * 存 public 磁盘下 storage/app/public/plots 的相对路径；未上传为 null，卡片渲染占位。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plots', function (Blueprint $table) {
            $table->string('cover_image', 500)->nullable()->after('story');
        });
    }

    public function down(): void
    {
        Schema::table('plots', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
