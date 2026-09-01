<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\AdoptionAdjustment;
use App\Models\Camera;
use App\Models\Delivery;
use App\Models\FarmLog;
use App\Models\GiftBox;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\ShortLink;
use App\Models\TraceCode;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * 商户后台首页：管理统计卡 + 经营看板（3.5：认养转化/续费意向/产出达标/溯源查看率）。
     * 租户上下文已注入，所有 count 自动按当前租户过滤。
     */
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        $year = (int) now()->format('Y');

        $adoptionCount = Adoption::count();
        $activeEnded = Adoption::whereIn('status', ['active', 'ended'])->count();
        $paid = Adoption::whereIn('status', ['active', 'ended', 'pending_agreement'])->count();

        // 产出达标：当前年度采收总量 vs 应达量（Σ 生效认养 × plan.delivery_rule.guarantee_kg）
        $harvestTotal = Harvest::where('season_year', $year)->sum('dry_weight_kg');
        $guaranteeTotal = Adoption::query()
            ->where('season_year', $year)
            ->whereIn('status', ['active', 'ended'])
            ->with('plan')
            ->get()
            ->sum(fn ($a) => (float) data_get($a->plan?->delivery_rule, 'guarantee_kg', 0));

        // 溯源查看率：被扫码箱数 / 总箱数
        $traceTotal = TraceCode::count();
        $traceScanned = TraceCode::where('scanned_count', '>', 0)->count();

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'plotCount' => Plot::count(),
            'adoptionCount' => $adoptionCount,
            'activeAdoptions' => Adoption::where('status', 'active')->count(),
            'pendingPaymentCount' => Adoption::where('status', 'pending_payment')->count(),
            'cameraCount' => Camera::count(),
            'farmLogCount' => FarmLog::count(),
            'deliveryCount' => Delivery::count(),
            'adjustmentCount' => AdoptionAdjustment::where('status', 'pending')->count(),
            'giftBoxCount' => GiftBox::count(),
            'shortLinkCount' => ShortLink::count(),
            'stats' => [
                'conversion' => $adoptionCount ? round($activeEnded / $adoptionCount * 100, 1) : 0,
                'paymentRate' => $adoptionCount ? round($paid / $adoptionCount * 100, 1) : 0,
                'renewalIntent' => Adoption::where('status', 'active')->where('auto_renew', true)->count(),
                'expiringSoon' => Adoption::where('status', 'active')->whereBetween('end_date', [now(), now()->addDays(30)])->count(),
                'harvestTotal' => $harvestTotal,
                'guaranteeTotal' => $guaranteeTotal,
                'attainment' => $guaranteeTotal > 0 ? round($harvestTotal / $guaranteeTotal * 100, 1) : null,
                'traceScanned' => $traceScanned,
                'traceTotal' => $traceTotal,
                'traceRate' => $traceTotal ? round($traceScanned / $traceTotal * 100, 1) : null,
                'traceScans' => TraceCode::sum('scanned_count'),
            ],
            'seasonStats' => $this->seasonStats($year),
        ]);
    }

    /** 按年度简表：认养单数 / 生效数 / 采收总量。 */
    private function seasonStats(int $fallbackYear): array
    {
        $years = Adoption::select('season_year')->distinct()->orderByDesc('season_year')->pluck('season_year');

        $stats = [];
        foreach ($years as $y) {
            $stats[] = [
                'year' => $y,
                'count' => Adoption::where('season_year', $y)->count(),
                'active' => Adoption::where('season_year', $y)->whereIn('status', ['active', 'ended'])->count(),
                'harvest_kg' => Harvest::where('season_year', $y)->sum('dry_weight_kg'),
            ];
        }

        if (empty($stats)) {
            $stats[] = ['year' => $fallbackYear, 'count' => 0, 'active' => 0, 'harvest_kg' => 0];
        }

        return $stats;
    }
}
