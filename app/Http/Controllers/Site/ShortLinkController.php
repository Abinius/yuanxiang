<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\ShortLinkService;

/**
 * 短链接跳转（公开）：/u/{code} → 302 目标。TenantScoped 自动隔离，跨租户码 404。
 */
class ShortLinkController extends Controller
{
    public function __construct(private readonly ShortLinkService $links)
    {
    }

    public function redirect(Tenant $tenant, string $code)
    {
        $link = $this->links->resolve($tenant, $code);
        abort_unless($link, 404);

        return redirect()->away($link->target_url, 302);
    }
}
