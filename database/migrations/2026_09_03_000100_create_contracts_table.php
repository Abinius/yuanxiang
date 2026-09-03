<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 认养合同：签约时生成合同实体（条款快照 + 编号 + 签约留痕）。
 * PDF 落盘字段 pdf_path 暂 nullable（v1 用 HTML 可打印视图，后续接 dompdf）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adoption_id')->constrained()->cascadeOnDelete();
            $table->string('contract_no');                       // 2026-guangcai-0001
            $table->string('template_version')->default('v1');   // 生成时条款版本，锁定
            $table->json('clauses');                             // [{title, body}] 快照
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();         // IPv6 兼容
            $table->string('pdf_path', 500)->nullable();
            $table->string('status')->default('signed');         // signed / voided
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_no']);
            $table->index('adoption_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
