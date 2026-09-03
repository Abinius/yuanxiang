<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 佣金流水：推荐人按 tier 比例分得的佣金，经冷却期转正、提现扣减。
 * 佣金率/冷却期/会员门槛全部读 tenants.settings（M2），不硬编码。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();    // 推荐人
            $table->foreignId('adoption_id')->constrained()->cascadeOnDelete();
            $table->string('tier');                                                // red / expert / partner
            $table->decimal('rate', 5, 2);                                         // 佣金率(%)
            $table->decimal('amount', 10, 2);                                      // 佣金(元)
            $table->string('status')->default('pending');                          // pending/available/frozen/settled
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['adoption_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_ledger');
    }
};
