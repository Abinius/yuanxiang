<?php

namespace App\Http\Controllers\Site;

use App\Enums\FarmLogType;
use DateTimeImmutable;

/**
 * F4 R4.1：渲染期系统节点（虚拟，不写库）。
 * 用于"我的田"农事动态流，在家人静默时以物候常识兜底陪伴感。
 * 仅承载 Blade 模板读取字段，可与 FarmLog 模型并列渲染。
 */
class SystemTimelineNode
{
    /** 渲染用标签（映射到 FarmLogType 以便复用 $log->type->label()）。 */
    public function __construct(
        public readonly FarmLogType $type,
        public readonly string $title,
        public readonly string $content,
        public readonly DateTimeImmutable $occurred_at,
        public readonly bool $is_system = true,
    ) {
    }

    public function source(): string
    {
        return 'system';
    }
}