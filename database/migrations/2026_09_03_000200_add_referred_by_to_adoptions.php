<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 推荐关系落地：谁推荐了谁、在这笔认养上。
 * 原 referral 仅为券机制，缺持久链路；佣金按 adoption 结算需落库。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->foreignId('referred_by_user_id')->nullable()->after('user_id')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn('referred_by_user_id');
        });
    }
};
