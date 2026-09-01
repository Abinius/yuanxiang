<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 3 运维：补退按年（3.2）、礼盒扫码码+发货（3.3）、续费链+券-订单关联（3.4）。
     */
    public function up(): void
    {
        // 3.2：补退按年度去重/查询
        Schema::table('adoption_adjustments', function (Blueprint $table) {
            $table->unsignedSmallInteger('season_year')->nullable()->after('adoption_id');
        });
        Schema::table('adoption_adjustments', function (Blueprint $table) {
            $table->index(['adoption_id', 'season_year']);
        });

        // 3.3：礼盒扫码唯一码 + 发货字段
        Schema::table('gift_boxes', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('year');
            $table->string('carrier')->nullable()->after('tracking_no');
            $table->timestamp('shipped_at')->nullable()->after('carrier');
            $table->timestamp('received_at')->nullable()->after('shipped_at');
        });

        // 3.3：草稿阶段收礼人未填，放宽为可空
        Schema::table('gift_boxes', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->change();
            $table->string('recipient_phone')->nullable()->change();
        });

        // 3.4：续费链（对齐 transferred_from_id/upgraded_from_id）
        Schema::table('adoptions', function (Blueprint $table) {
            $table->foreignId('renewed_from_id')->nullable()->after('upgraded_from_id')->constrained('adoptions')->nullOnDelete();
        });

        // 3.4：券-订单关联（加表不改表）
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adoption_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_off', 10, 2)->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');

        Schema::table('adoptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewed_from_id');
        });

        Schema::table('gift_boxes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'carrier', 'shipped_at', 'received_at']);
        });

        Schema::table('adoption_adjustments', function (Blueprint $table) {
            $table->dropIndex(['adoption_id', 'season_year']);
            $table->dropColumn('season_year');
        });
    }
};
