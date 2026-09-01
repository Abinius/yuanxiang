<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 支付（多态）/ 配送 / 节日礼盒 / 溯源码 / 缺产补退
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 10, 2);
            $table->string('method')->default('wechat');    // wechat / manual / other
            $table->string('transaction_id')->nullable();   // 微信交易号
            $table->string('status')->default('pending');   // pending / paid / refunded
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refund_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adoption_id')->constrained()->cascadeOnDelete();
            $table->foreignId('harvest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('spec')->nullable();               // 规格/包装偏好
            $table->string('tracking_no')->nullable();
            $table->string('carrier')->nullable();
            $table->string('status')->default('pending');   // pending / shipped / delivered
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('gift_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adoption_id')->constrained()->cascadeOnDelete();
            $table->string('festival');                     // spring / dragon_boat / mid_autumn
            $table->unsignedSmallInteger('year');
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->string('signature_image')->nullable();  // 亲笔签图片
            $table->text('message')->nullable();            // 寄语
            $table->string('status')->default('draft');     // draft / making / shipped / delivered
            $table->string('tracking_no')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('trace_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();               // 每箱一码
            $table->foreignId('adoption_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('harvest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained('plots')->nullOnDelete();
            $table->unsignedInteger('scanned_count')->default(0);
            $table->string('chain_hash')->nullable();       // V2/V3
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('adoption_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adoption_id')->constrained()->cascadeOnDelete();
            $table->string('type');                         // compensate_kg / refund_prorated / defer
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('kg', 8, 2)->nullable();
            $table->text('reason')->nullable();             // 低于保底 / 不可抗力
            $table->string('status')->default('pending');   // pending / applied
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoption_adjustments');
        Schema::dropIfExists('trace_codes');
        Schema::dropIfExists('gift_boxes');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('payments');
    }
};
