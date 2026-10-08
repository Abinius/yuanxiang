<?php

namespace App\Models\Concerns;

use UnitEnum;

/**
 * 状态机：状态转移规则写在各 Status 枚举的 transitions() 里，
 * 本 trait 只做校验与落库——非法转移统一 422，不再各服务手抄前置校验。
 *
 * 规则来源即状态机图正文（见 PRD §6 #13 产出物）：改转移要改 enum，不要改调用点。
 */
trait HasStatusTransitions
{
    /** 是否允许转移到 $to（只判断，不落地；读路径用它放行/拒绝，不抛异常）。 */
    public function canTransitionTo(UnitEnum $to): bool
    {
        return in_array($to, $this->status->transitions(), true);
    }

    /**
     * 转移状态并落库；非法转移 422。
     *
     * @param  array<string, mixed>  $attributes  同一次保存顺带写入的其他字段（如 named_label/shipped_at）
     */
    public function transitionTo(UnitEnum $to, array $attributes = []): void
    {
        $current = $this->status;

        abort_unless(
            in_array($to, $current->transitions(), true),
            422,
            "当前状态「{$current->label()}」不可转移到「{$to->label()}」"
        );

        $this->update(array_merge(['status' => $to->value], $attributes));
    }
}
