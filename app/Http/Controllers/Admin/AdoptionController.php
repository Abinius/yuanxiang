<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
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

    /** A1 离线开单：选用户（手机号）+ 地块 + 方案。 */
    public function create(Tenant $tenant, Request $request)
    {
        $plots = Plot::query()
            ->where('status', 'available')
            ->with('plan')
            ->orderBy('code')
            ->get();

        return view('admin.adoptions.create', compact('tenant', 'plots'));
    }

    /** A1 离线开单提交：复用 AdoptionService::createOrder + markPaid（线下已收款）。 */
    public function store(Tenant $tenant, Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^1\d{10}$/'],
            'plot_id' => ['required', 'exists:plots,id'],
            'season_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'named_label' => ['nullable', 'string', 'max:30'],
        ]);

        $plot = Plot::findOrFail($data['plot_id']); // TenantScoped 上下文内自动过滤
        abort_if($plot->status !== \App\Enums\PlotStatus::Available, 422, '该田块当前不可认养');

        $user = User::where('tenant_id', $tenant->id)->where('phone', $data['phone'])->first();
        abort_if(! $user, 422, '未找到该手机号的云乡民');

        $adoption = $this->adoptions->createOrder($user, $plot, [], [
            'season_year' => $data['season_year'] ?? (int) now()->format('Y'),
        ]);

        // 线下已收款 → 直接标记已支付（→ 待签约），管理员可顺手命名
        $this->adoptions->markPaid($adoption, ['method' => 'offline']);

        if (! empty($data['named_label'])) {
            $this->adoptions->signAgreement($adoption, $data['named_label']);
        }

        return redirect()->route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug])
            ->with('ok', '已创建离线订单 '.$adoption->adoption_no);
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
