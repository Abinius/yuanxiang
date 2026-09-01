<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2.7 推送记录：模板消息（开播提醒 / 内容动态）。
     * mock 模式（config('wechat.mock')）只落库不真发；P6 服务号配置到位后真发。
     */
    public function up(): void
    {
        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default('wechat_template');
            $table->string('template_id')->nullable();
            $table->string('type');                 // live_notice / content
            $table->json('payload')->nullable();
            $table->string('status')->default('queued'); // queued / sent / failed
            $table->text('errmsg')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
    }
};
