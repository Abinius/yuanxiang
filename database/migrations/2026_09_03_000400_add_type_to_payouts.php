<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 payouts 扩展：区分 farm 分账(settlement) 与 用户佣金提现(commission)，
 * 并支持关联 user_id（原表只有 farm_id，是农户分账的）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payouts', 'type')) {
                $table->string('type')->default('settlement')->after('tenant_id'); // settlement / commission
            }
            if (! Schema::hasColumn('payouts', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('farm_id')
                      ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['type', 'user_id']);
        });
    }
};
