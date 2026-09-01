<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SaaS 底座：运营公司主体、平台套餐、租户、农场/基地、认养方案
     */
    public function up(): void
    {
        // 运营公司主体（交易 / 内容）
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->default('platform'); // platform / tenant / content
            $table->string('tax_no')->nullable();
            $table->string('bank')->nullable();
            $table->string('wx_mch_id')->nullable();
            $table->string('food_license_no')->nullable();
            $table->text('license_scope')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // 平台套餐（V2 计费启用，建表占位）
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price_yearly')->default(0);
            $table->json('limits')->nullable();                 // {tenants, plots, cameras, ...}
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // 租户（SaaS 底座；路径 /t/{slug}）
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('operator_org_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->json('settings')->nullable();               // 主题 token 覆盖 / 费率 / 域名
            $table->string('status')->default('trial');         // trial / active / suspended / expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 农场 / 供应基地（后期接全村/国内/国外 = 加行）
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_org_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->string('owner_name')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable()->index(); // 软引用（避免跨迁移外键环）
            $table->string('region')->nullable();
            $table->string('country')->default('中国');
            $table->string('cert_status')->default('not_started'); // not_started / converting / certified
            $table->timestamp('cert_expires_at')->nullable();
            $table->string('cert_doc_url')->nullable();
            $table->string('settle_bank')->nullable();
            $table->string('settle_account')->nullable();
            $table->json('export_qualifications')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 认养方案（怎么卖，可配置；B端/农户差异定价只加行）
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                             // 一分地 / 单株 / 整亩B端 / 家禽 / 农旅
            $table->string('subject_type')->default('plot');    // plot / livestock_batch / tour_slot
            $table->unsignedInteger('price_yearly')->default(0);
            $table->json('delivery_rule')->nullable();          // 丰欠共担 / 保底 json（DB 设计 §4.1）
            $table->json('benefits')->nullable();
            $table->json('festival_quota')->nullable();         // 三节礼盒次数与用料
            $table->string('stock_mode')->default('quota');     // quota / unlimited
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
        Schema::dropIfExists('farms');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('organizations');
    }
};
