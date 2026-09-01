<?php

namespace App\Http\Controllers\Site;

use App\Enums\AdoptionStatus;
use App\Enums\DeliveryStatus;
use App\Enums\PlotType;
use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Delivery;
use App\Models\FarmLog;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\DeliveryService;
use App\Services\PromotionService;
use Illuminate\Http\Request;

/**
 * 我的田（云乡民视角）：铭牌 + 生长日历 + 农事动态流 + 铭牌分享。
 *
 * 路由挂在租户组下 /my，全部 auth + owner-gated。
 * 注意路由-param 位置性：方法签名 Tenant 在前、Adoption 在后，
 * 因 SubstituteBindings 先于 TenantMiddleware，TenantScope 在绑定时未激活，
 * 故保留显式 tenant_id/user_id 守卫。
 */
class MyPlotController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveries,
        private readonly PromotionService $promotions,
    ) {
    }

    /** 我的认养列表。 */
    public function index(Tenant $tenant, Request $request)
    {
        $adoptions = $request->user()->adoptions()->latest()->get();

        return view('site.my.index', [
            'tenant' => $tenant,
            'adoptions' => $adoptions,
        ]);
    }

    /** 我的田主页：铭牌 + 12 月生长日历 + 农事动态流。 */
    public function show(Tenant $tenant, Adoption $adoption, Request $request)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);
        abort_unless($adoption->status === AdoptionStatus::Active, 403, '认养未生效');

        $adoption->load(['adoptable', 'deliveries.address', 'deliveries.harvest']);
        $plotIds = $this->relevantPlotIds($adoption->adoptable);

        $logs = FarmLog::query()
            ->where('is_public', true)
            ->whereIn('plot_id', $plotIds)
            ->with('author')
            ->orderByDesc('occurred_at')
            ->limit(30)
            ->get();

        return view('site.my.plot', [
            'tenant' => $tenant,
            'adoption' => $adoption,
            'logs' => $logs,
            'calendar' => $this->buildCalendar($logs),
        ]);
    }

    /** 独立铭牌页（截图分享 / 复制链接用）。 */
    public function nameplate(Tenant $tenant, Adoption $adoption, Request $request)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);
        abort_unless($adoption->status === AdoptionStatus::Active, 403, '认养未生效');

        $adoption->load('adoptable');

        return view('site.my.nameplate', [
            'tenant' => $tenant,
            'adoption' => $adoption,
        ]);
    }

    /** C 端确认收货（owner-gated + 状态守卫，仅已发货可签收）。 */
    public function receive(Tenant $tenant, Adoption $adoption, Delivery $delivery, Request $request)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);
        abort_if($delivery->tenant_id !== $tenant->id || $delivery->adoption_id !== $adoption->id, 404);
        abort_unless($delivery->status === DeliveryStatus::Shipped, 422, '仅已发货可确认收货');

        $this->deliveries->markReceived($delivery);

        return back()->with('ok', '已确认收货');
    }

    /** 续费：建下一季新单（自动用用户可用的 renewal 券抵扣）。 */
    public function renew(Tenant $tenant, Adoption $adoption, Request $request)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);
        abort_unless($adoption->status === AdoptionStatus::Active, 422, '仅生效中可续费');

        $coupon = $request->user()->coupons()
            ->where('status', 'unused')
            ->whereHas('promotion', fn ($q) => $q->where('type', 'renewal')->where('status', 'active'))
            ->first();

        $new = $this->promotions->renew($request->user(), $adoption, $coupon);

        return redirect()->route('tenant.adopt.pay', ['tenant' => $tenant->slug, 'adoption' => $new]);
    }

    /** 续费意向开关。 */
    public function autoRenew(Tenant $tenant, Adoption $adoption, Request $request)
    {
        abort_if($adoption->tenant_id !== $tenant->id || $adoption->user_id !== $request->user()->id, 404);

        $adoption->update(['auto_renew' => ! $adoption->auto_renew]);

        return back()->with('ok', $adoption->auto_renew ? '已开启续费意向' : '已关闭续费意向');
    }

    /**
     * 三种 adoptable 类型的相关 plot_id 集合（动态流查询范围）。
     * 分地→自身；拼团田→组+其下株；单株→株+父组。
     * v1 用 plot_id 覆盖，不依赖 plant_id 软引用。
     */
    private function relevantPlotIds(Plot $plot): array
    {
        return match ($plot->type) {
            PlotType::Group => array_merge(
                [$plot->id],
                $plot->children()->pluck('id')->all(),
            ),
            PlotType::Plant => array_filter([$plot->id, $plot->parent_plot_id], fn ($v) => $v !== null),
            PlotType::Plot => [$plot->id],
        };
    }

    /** 12 月生长日历：每月阶段色 + 当月农事事件计数 + 今天标记。 */
    private function buildCalendar($logs): array
    {
        $stages = config('goji.stages');
        $todayMonth = (int) now()->format('n');

        $counts = [];
        foreach ($logs as $log) {
            $m = (int) $log->occurred_at->format('n');
            $counts[$m] = ($counts[$m] ?? 0) + 1;
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $stage = $stages[$m] ?? ['label' => '', 'color' => '#e5dfd5'];
            $months[] = [
                'month' => $m,
                'label' => $stage['label'],
                'color' => $stage['color'],
                'events' => $counts[$m] ?? 0,
                'is_today' => $m === $todayMonth,
            ];
        }

        return ['months' => $months, 'today_month' => $todayMonth];
    }
}
