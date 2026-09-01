<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 平台抽佣/分账（V2 接合作农户启用，MVP 建表占位）
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adoption_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);               // 认养收入
            $table->decimal('commission_amount', 10, 2)->default(0); // 平台抽佣
            $table->decimal('farm_amount', 10, 2)->default(0);      // 农户/基地分账
            $table->string('status')->default('pending');   // pending / settled
            $table->timestamp('settled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method')->nullable();
            $table->string('status')->default('pending');   // pending / paid / failed
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->decimal('commission_rate', 5, 2);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('settlements');
    }
};
