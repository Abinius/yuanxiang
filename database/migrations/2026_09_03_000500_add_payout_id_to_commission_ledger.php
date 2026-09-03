<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 提现-流水关联：cashOut 扣减的流水行打上 payout_id，
 * admin 驳回提现时可精准回流（settled → available）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_ledger', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('adoption_id')
                  ->constrained('payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_ledger', function (Blueprint $table) {
            $table->dropForeign(['payout_id']);
            $table->dropColumn('payout_id');
        });
    }
};
