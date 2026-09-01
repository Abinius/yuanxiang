<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 3.4 促销管理（tenant_admin）：新建活动 + 查看已发券。
 */
class PromotionController extends Controller
{
    public function index(Tenant $tenant, Request $request)
    {
        $promotions = Promotion::query()->orderByDesc('id')->get();
        $coupons = Coupon::query()->with(['user', 'promotion'])->orderByDesc('id')->limit(100)->get();

        return view('admin.promotions.index', compact('tenant', 'promotions', 'coupons'));
    }

    public function create(Tenant $tenant, Request $request)
    {
        return view('admin.promotions.form', compact('tenant'));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(['new_customer', 'renewal', 'referral', 'upgrade', 'festival'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ]);

        Promotion::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'rule' => $data['amount'] !== null && $data['amount'] !== ''
                ? ['amount' => (float) $data['amount']]
                : ['percent' => (float) $data['percent']],
            'stock' => $data['stock'] ?? null,
            'status' => 'active',
        ]);

        return redirect()->route('tenant.admin.promotions.index', ['tenant' => $tenant->slug])
            ->with('ok', '促销已创建');
    }
}
