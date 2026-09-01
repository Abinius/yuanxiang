<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShortLink;
use App\Models\Tenant;
use App\Services\ShortLinkService;
use Illuminate\Http\Request;

/**
 * 短链接管理（tenant_admin）：生成任意分享短链（可自定义码）+ 点击统计。
 */
class ShortLinkController extends Controller
{
    public function __construct(private readonly ShortLinkService $links)
    {
    }

    public function index(Tenant $tenant, Request $request)
    {
        $shortLinks = ShortLink::query()->orderByDesc('id')->get();

        return view('admin.short_links.index', compact('tenant', 'shortLinks'));
    }

    public function create(Tenant $tenant, Request $request)
    {
        return view('admin.short_links.create', compact('tenant'));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $data = $request->validate([
            'target_url' => ['required', 'string', 'max:500'],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $link = $this->links->create($tenant, $data['target_url'], $data['code'] ?? null);

        return redirect()->route('tenant.admin.short-links.index', ['tenant' => $tenant->slug])
            ->with('ok', '短链已生成：/u/'.$link->code);
    }
}
