<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Tenant;
use App\Services\AdoptionService;
use App\Services\WeChatPayService;
use Illuminate\Http\Request;

class AdoptionController extends Controller
{
    public function __construct(
        private readonly WeChatPayService $pay,
        private readonly AdoptionService $adoptions,
    ) {
    }

    /** 订单列表（租户内，TenantScoped 自动过滤；状态筛选 + 分页）。 */
    public function index(Tenant $tenant, Request $request)
    {
        $adoptions = Adoption::query()
            ->with(['user', 'adoptable', 'plan', 'payments'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.adoptions.index', compact('tenant', 'adoptions'));
    }

    /**
     * 后台退款：先向微信发起退款，再落库（已支付→已退款，认养→取消）。
     */
    public function refund(Tenant $tenant, Adoption $adoption)
    {
        abort_if($adoption->tenant_id !== $tenant->id, 404);

        $this->pay->requestRefund($adoption);
        $this->adoptions->markRefunded($adoption);

        return back()->with('status', '退款已发起');
    }
}
