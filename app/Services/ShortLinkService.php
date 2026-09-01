<?php

namespace App\Services;

use App\Models\ShortLink;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * 通用短链接：任意分享 URL 缩短（6 位随机或自定义码）+ 点击计数。
 * /u/{code} 302 跳转；TenantScoped 自动租户隔离。
 */
class ShortLinkService
{
    public function create(Tenant $tenant, string $targetUrl, ?string $code = null): ShortLink
    {
        return ShortLink::create([
            'tenant_id' => $tenant->id,
            'code' => $this->uniqueCode($code),
            'target_url' => $targetUrl,
        ]);
    }

    public function resolve(Tenant $tenant, string $code): ?ShortLink
    {
        $link = ShortLink::where('code', $code)->first(); // TenantScoped 自动隔离
        if ($link) {
            $link->increment('click_count');
        }

        return $link;
    }

    public function makeUrl(Tenant $tenant, ShortLink $link): string
    {
        return route('tenant.short-link.redirect', ['tenant' => $tenant->slug, 'code' => $link->code]);
    }

    private function uniqueCode(?string $custom = null): string
    {
        if ($custom) {
            abort_if(ShortLink::where('code', $custom)->exists(), 422, '短码已存在');
            abort_if(! preg_match('/^[a-z0-9-]{2,20}$/', $custom), 422, '短码需 2-20 位小写字母/数字/连字符');

            return $custom;
        }

        for ($i = 0; $i < 5; $i++) {
            $code = Str::lower(Str::random(6));
            if (! ShortLink::where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('短码生成碰撞');
    }
}
