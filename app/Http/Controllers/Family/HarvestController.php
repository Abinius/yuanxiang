<?php

namespace App\Http\Controllers\Family;

use App\Jobs\SendHarvestNoticeJob;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 家人端：录入采收（harvests）。
 * scope=harvest；handler_id=当前 user；season_year 默认本年。
 */
class HarvestController extends Controller
{
    public function create(Tenant $tenant, Request $request)
    {
        $this->assertScope($request, 'harvest');

        $plots = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')->orderBy('code')->get();

        return view('family.harvest.create', compact('tenant', 'plots'));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'harvest');

        $data = $request->validate([
            'plot_id' => ['required', Rule::exists('plots', 'id')->where('tenant_id', $tenant->id)],
            'season_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'harvested_at' => ['required', 'date'],
            'dry_weight_kg' => ['required', 'numeric', 'min:0'],
            'quality_grade' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $harvest = new Harvest();
        $harvest->tenant_id = $tenant->id;
        $harvest->farm_id = $member->farm_id;
        $harvest->plot_id = $data['plot_id'];
        $harvest->season_year = $data['season_year'] ?? now()->year;
        $harvest->harvested_at = $data['harvested_at'];
        $harvest->dry_weight_kg = $data['dry_weight_kg'];
        $harvest->quality_grade = $data['quality_grade'] ?? null;
        $harvest->handler_id = $request->user()->id;
        $harvest->notes = $data['notes'] ?? null;
        $harvest->save();

        // 3.1：采收通知该田块认养人「你的田今天采了」（queue 消费，mock 只落库）
        SendHarvestNoticeJob::dispatch($harvest->id);

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', '采收已记录');
    }
}
