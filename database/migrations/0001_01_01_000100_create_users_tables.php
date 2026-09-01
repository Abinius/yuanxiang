<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 统一账号（双登录：微信一键 openid + 账号密码 phone/username）＋会话
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('phone')->nullable()->unique();      // 账号密码登录主键
            $table->string('username')->nullable()->unique();   // 替代登录名
            $table->string('nickname')->nullable();
            $table->string('avatar')->nullable();
            $table->string('real_name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();             // 纯微信用户可无密码
            $table->string('openid')->nullable()->index();      // 微信一键登录
            $table->string('unionid')->nullable()->index();
            $table->string('role')->default('villager');        // villager / family / tenant_admin / platform_admin
            $table->string('village_card_no')->nullable()->unique();
            $table->unsignedSmallInteger('joined_year')->nullable();
            $table->timestamp('password_set_at')->nullable();   // 设密时间（审计）
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
