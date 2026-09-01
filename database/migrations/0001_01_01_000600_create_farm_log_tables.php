<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 有机肥批次 / 农事动态+溯源（一表两视图）/ 检测报告 / 采收
     */
    public function up(): void
    {
        Schema::create('fertilizer_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('produced_at')->nullable();
            $table->string('nxlb_ref')->nullable();      // NXLB 姐夫厂信息
            $table->text('ingredients')->nullable();
            $table->string('test_report_url')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'batch_no']);
        });

        Schema::create('farm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained('plots')->nullOnDelete();
            $table->unsignedBigInteger('plant_id')->nullable(); // 株级（软引用）
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');                      // fertilize / weed / prune / harvest / inspect / live_broadcast / daily
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->json('images')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('fertilizer_batch_id')->nullable()->constrained('fertilizer_batches')->nullOnDelete();
            $table->boolean('is_trace_node')->default(false); // 是否进溯源时间线
            $table->boolean('is_public')->default(true);      // 是否对云乡民可见
            $table->string('source')->default('family');      // family / operator / villager
            $table->json('payload')->nullable();              // 扩展节点数据
            $table->string('lang')->default('zh');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['plot_id', 'occurred_at']);
        });

        Schema::create('detection_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained('plots')->nullOnDelete();
            $table->string('report_no');
            $table->string('type');                      // pesticide / heavy_metal / quality / organic
            $table->string('batch_ref')->nullable();     // 关联 harvest 或 fertilizer_batch
            $table->string('org')->nullable();           // 检测机构
            $table->string('report_url')->nullable();
            $table->json('result_summary')->nullable();  // 关键指标
            $table->boolean('qualified')->default(false);
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'report_no']);
        });

        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('plot_id')->constrained('plots')->cascadeOnDelete();
            $table->unsignedSmallInteger('season_year');
            $table->date('harvested_at')->nullable();
            $table->decimal('dry_weight_kg', 8, 2)->nullable();
            $table->string('quality_grade')->nullable();
            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
        Schema::dropIfExists('detection_reports');
        Schema::dropIfExists('farm_logs');
        Schema::dropIfExists('fertilizer_batches');
    }
};
