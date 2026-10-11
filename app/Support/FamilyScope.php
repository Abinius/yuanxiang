<?php

namespace App\Support;

/**
 * 家人端录入权限词表（唯一出处）。
 *
 * 后台账号配置、家人端看板卡片、Family\Controller 的限权守卫都从这里取；
 * 新增一类录入（如巡检）时只改这一处，不会像过去那样漏掉看板或词表。
 */
final class FamilyScope
{
    /** 全部录入权限项；tenant_admin 直通这一份。 */
    public const ALL = ['farm_log', 'fertilizer', 'harvest', 'plot'];

    /** 权限项 → 展示名。 */
    public static function labels(): array
    {
        return [
            'farm_log' => '农事动态 / 直播预告',
            'fertilizer' => '有机肥批次',
            'harvest' => '采收',
            'plot' => '地块录入',
        ];
    }

    /** 家人身份关系（farm_members.relation）。 */
    public static function relations(): array
    {
        return [
            'father' => '父亲',
            'mother' => '母亲',
            'brother' => '兄弟',
            'sister' => '姐妹',
            'staff' => '村里员工',
        ];
    }
}
