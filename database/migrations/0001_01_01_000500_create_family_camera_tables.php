<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 在地家人/农户员工（按 scope 限权）＋ 摄像头
     */
    public function up(): void
    {
        Schema::create('farm_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('relation');                 // father / brother / mother / staff
            $table->json('permission_scope')->nullable(); // ["farm_log","fertilizer","harvest"]
            $table->timestamps();

            $table->unique(['user_id', 'farm_id']);
        });

        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained('plots')->nullOnDelete();
            $table->string('name');
            $table->string('device_no')->nullable();
            $table->string('provider')->default('ezviz'); // ezviz(萤石) / aliyun / other
            $table->string('stream_url')->nullable();
            $table->string('playback_url')->nullable();
            $table->string('token')->nullable();
            $table->string('status')->default('offline'); // online / offline
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
        Schema::dropIfExists('farm_members');
    }
};
