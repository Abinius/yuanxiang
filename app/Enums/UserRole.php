<?php

namespace App\Enums;

enum UserRole: string
{
    case Villager = 'villager';          // 云乡民
    case Family = 'family';              // 家人/农户员工
    case TenantAdmin = 'tenant_admin';   // 租户管理员
    case PlatformAdmin = 'platform_admin'; // 平台管理员

    public function label(): string
    {
        return match ($this) {
            self::Villager => '云乡民',
            self::Family => '家人',
            self::TenantAdmin => '商户管理员',
            self::PlatformAdmin => '平台管理员',
        };
    }
}
