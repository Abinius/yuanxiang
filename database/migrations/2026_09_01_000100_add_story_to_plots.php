<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1 地块故事：地块详情页展示的种植故事/介绍文案，由 admin/family 录入。
 * 纯展示字段，不参与业务逻辑。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plots', function (Blueprint $table) {
            $table->text('story')->nullable()->after('price_yearly');
        });
    }

    public function down(): void
    {
        Schema::table('plots', function (Blueprint $table) {
            $table->dropColumn('story');
        });
    }
};