<?php

namespace App\Http\Controllers\Family;

use App\Enums\FarmLogType;
use App\Jobs\SendFarmLogNoticeJob;
use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 家人端：发农事动态 / 直播预告（farm_logs，type 含 LiveBroadcast）。
 * scope=farm_log；图片入本地盘 farm-logs/；is_public 默认公开（喂"我的田"动态流）。
 * 2.5：施肥/采收/检测自动 is_trace_node=true（进溯源时间线），施肥节点可挂 NXLB 批次。
 */
class FarmLogController extends Controller
{
    public function create(Tenant $tenant, Request $request)
    {
        $this->assertScope($request, 'farm_log');

        $plots = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')->orderBy('code')->get();
        $types = collect(FarmLogType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all();
        $batches = FertilizerBatch::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('batch_no')
            ->get(['id', 'batch_no', 'produced_at']);

        return view('family.farm_log.create', compact('tenant', 'plots', 'types', 'batches'));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'farm_log');

        $data = $request->validate([
            'plot_id' => ['required', Rule::exists('plots', 'id')->where('tenant_id', $tenant->id)],
            'type' => ['required', Rule::enum(FarmLogType::class)],
            'fertilizer_batch_id' => ['nullable', 'integer', Rule::exists('fertilizer_batches', 'id')->where('tenant_id', $tenant->id)],
            'title' => ['required', 'string', 'max:60'],
            'content' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
            'is_public' => ['boolean'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('farm-logs', 'public');
            }
        }

        $log = new FarmLog();
        $log->tenant_id = $tenant->id;
        $log->farm_id = $member->farm_id;
        $log->plot_id = $data['plot_id'];
        $log->author_id = $request->user()->id;
        $log->type = $data['type'];
        $log->title = $data['title'];
        $log->content = $data['content'] ?? '';
        $log->images = $images;
        $log->video_url = null;
        $log->occurred_at = $data['occurred_at'] ?? now();
        $log->is_public = $request->boolean('is_public');
        $log->source = 'family';
        // 2.5：施肥/采收/检测自动进溯源时间线
        $log->is_trace_node = in_array($data['type'], ['fertilize', 'harvest', 'inspect'], true);
        // NXLB 批次仅施肥节点挂接（非施肥记录一律不挂）
        $log->fertilizer_batch_id = $data['type'] === FarmLogType::Fertilize->value
            ? ($data['fertilizer_batch_id'] ?? null)
            : null;
        $log->save();

        // 2.7：直播预告 / 内容动态公开 → 推送云乡民（queue 消费，mock 只落库）
        if ($log->is_public && in_array($log->type->value, ['live_broadcast', 'daily'], true)) {
            SendFarmLogNoticeJob::dispatch($log->id);
        }

        $label = FarmLogType::from($data['type'])->label();

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', $label.'已发布');
    }
}
