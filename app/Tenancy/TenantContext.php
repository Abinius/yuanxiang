<?php

namespace App\Tenancy;

/**
 * 当前请求的租户上下文（1.4 由 TenantMiddleware 注入；null = 平台上下文，不过滤）
 */
final class TenantContext
{
    private static ?int $tenantId = null;

    public static function set(?int $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function id(): ?int
    {
        return self::$tenantId;
    }

    public static function has(): bool
    {
        return self::$tenantId !== null;
    }

    public static function reset(): void
    {
        self::$tenantId = null;
    }
}
