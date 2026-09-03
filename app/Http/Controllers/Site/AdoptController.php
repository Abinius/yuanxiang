<?php

namespace App\Http\Controllers\Site;

use App\Enums\AdoptionStatus;
use App\Enums\PlotType;
use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\AdoptionService;
use App\Services\PromotionService;
use App\Services\WeChatPayService;
use Illuminate\Http\Request;

class AdoptController extends Controller
{
    private const STATUS_LABELS = [
        'available' => '可认养',
        'adopted' => '已认养',
        'sold_out' => '售罄',
        'offline' => '下架',
    ];

    public function __construct(
        private readonly AdoptionService $adoptions,
        private readonly WeChatPayService $pay,
        private readonly PromotionService $promotions,
    ) {
    }

    public function index(Request $request, Tenant $tenant)
    {
        // F5：转化前回放 —— 公开的直播/解说内容（video_url 非空）在认养页可见，信任卖点
        $replays = \App\Models\FarmLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_public', true)
            ->whereIn('type', ['live_broadcast', 'explain'])
            ->whereNotNull('video_url')
            ->with(['plot', 'author'])
            ->orderByDesc('occurred_at')
            ->limit(4)
            ->get();

        return view('site.adopt.index', [
            'tenant' => $tenant,
            'plots' => Plot::where('type', 'plot')->orderBy('order_index')->get(),
            'groups' => Plot::where('type', 'group')->withCount('children')->orderBy('order_index')->get(),
            'statusLabels' => self::STATUS_LABELS,
            'replays' => $replays,
        ]);
    }

    public function show(Request $request, Tenant $tenant, Plot $plot)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        if ($plot->type === PlotType::Group) {
            $plants = Plot::where('parent_plot_id', $plot->id)->orderBy('order_index')->get();

            return view('site.adopt.group', [
                'tenant' => $tenant,
                'plot' => $plot,
                'plants' => $plants,
                'statusLabels' => self::STATUS_LABELS,
            ]);
        }

        return view('site.adopt.show', [
            'tenant' => $tenant,
            'plot' => $plot,
            'plan' => $plot->plan,
            'statusLabels' => self::STATUS_LABELS,
            'seo' => ['description' => $plot->code.' · 认养 '.number_format($plot->price_yearly).' 元/年 · 宁夏红寺堡枸杞认养，生态种植全程可溯源'],
        ]);
    }

    public function order(Request $request, Tenant $tenant, Plot $plot)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string'],
            'phone' => ['required', 'regex:/^1\d{10}$/'],
            'province' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'district' => ['nullable', 'string'],
            'detail' => ['required', 'string'],
            'referral_code' => ['nullable', 'string', 'max:40'],
        ]);

        $adoption = $this->adoptions->createOrder($request->user(), $plot, $data);

        // 3.4 + M4：下单填推荐码 → 新客/推荐人各得券 + 记录推荐关系（佣金据此结算）
        if (! empty($data['referral_code'])) {
            $referrer = $this->promotions->redeemReferral($data['referral_code'], $request->user());
            if ($referrer) {
                $adoption->update(['referred_by_user_id' => $referrer->id]);
            }
        }

        return redirect()->route('tenant.adopt.pay', ['tenant' => $tenant->slug, 'adoption' => $adoption]);
    }

    public function pay(Request $request, Tenant $tenant, Adoption $adoption)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);

        return view('site.adopt.pay', compact('tenant', 'adoption'));
    }

    public function confirmPay(Request $request, Tenant $tenant, Adoption $adoption)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);

        $this->adoptions->confirmMockPayment($adoption);

        return redirect()->route('tenant.adopt.success', ['tenant' => $tenant->slug, 'adoption' => $adoption]);
    }

    /**
     * 微信 JSAPI 下单：返回 WeixinJSBridge.invoke('getBrandWCPayRequest', ...) 所需参数。
     */
    public function wechatPay(Request $request, Tenant $tenant, Adoption $adoption)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);
        abort_unless($adoption->status === AdoptionStatus::PendingPayment, 422, '当前状态不支持支付');

        $openid = $request->user()->openid;
        abort_if(empty($openid), 422, '未取得微信 openid，请在微信客户端登录后支付');

        return response()->json($this->pay->jsapi($adoption, $openid));
    }

    public function success(Request $request, Tenant $tenant, Adoption $adoption)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);

        return view('site.adopt.success', compact('tenant', 'adoption'));
    }

    public function signAgreement(Request $request, Tenant $tenant, Adoption $adoption)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);

        $data = $request->validate([
            'named_label' => ['required', 'string', 'max:30'],
        ]);

        $this->adoptions->signAgreement($adoption, $data['named_label'], $request->ip());

        return redirect()->route('tenant.adopt.success', ['tenant' => $tenant->slug, 'adoption' => $adoption]);
    }
}
