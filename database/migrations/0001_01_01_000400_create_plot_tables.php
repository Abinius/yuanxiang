<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 认养单元：分地档 / 拼团田 / 株（自引用，统一 plots 表）
     */
    public function up(): void
    {
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('parent_plot_id')->nullable()->constrained('plots')->nullOnDelete(); // 株→拼团田
            $table->string('type');                       // plot(分地档) / group(拼团田) / plant(株)
            $table->string('code');                       // 地块编号 FD-A01 / PT-01 / Z-01-015
            $table->decimal('mu_area', 6, 2)->default(0); // 面积（亩）
            $table->unsignedInteger('price_yearly')->nullable(); // 年费快照（主档位在 plans）
            $table->string('status')->default('available');     // available / adopted / sold_out / offline
            $table->unsignedInteger('order_index')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plots');
    }
};
