<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 认养关系（多态标的）＋ 收货地址
     */
    public function up(): void
    {
        Schema::create('adoptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('adoption_no')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('adoptable_type');               // 多态：App\Models\Plot / LivestockBatch / TourSlot
            $table->unsignedBigInteger('adoptable_id');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->unsignedSmallInteger('season_year');
            $table->unsignedInteger('annual_fee');          // 实付年费（快照）
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('named_label')->nullable();      // 云乡民给地块命名
            $table->timestamp('agreement_signed_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('transferred_from_id')->nullable(); // 转赠链
            $table->unsignedBigInteger('upgraded_from_id')->nullable();    // 升档链
            $table->string('status')->default('pending_payment'); // pending_payment / pending_agreement / active / ended / cancelled
            $table->string('chain_hash')->nullable();       // 区块链存证（V2/V3 启用）
            $table->string('tx_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['adoptable_type', 'adoptable_id']);
            $table->index('user_id');
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('detail');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('adoptions');
    }
};
