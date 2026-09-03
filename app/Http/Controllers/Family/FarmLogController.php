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

        // G2：常用地块置顶（该家人最近 3 次录入的 plot_id，其余按 code）
        $recentIds = FarmLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('author_id', $request->user()->id)
            ->whereNotNull('plot_id')
            ->orderByDesc('occurred_at')
            ->limit(3)
            ->pluck('plot_id')
            ->unique()
            ->values();
        $recentOrder = $recentIds->flip(); // plot_id → 0(最近),1,2
        $plots = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')
            ->orderBy('code')
            ->get()
            ->sortBy(fn ($p) => $recentOrder->get($p->id, PHP_INT_MAX))
            ->values();
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
            'title' => ['nullable', 'string', 'max:60'],
            'content' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
            'is_public' => ['boolean'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video_url' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:40960'],
            'video_duration' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('farm-logs', 'public');
            }
        }

        $videoUrl = $request->hasFile('video_url') ? $request->file('video_url')->store('farm-logs', 'public') : null;

        $log = new FarmLog();
        $log->tenant_id = $tenant->id;
        $log->farm_id = $member->farm_id;
        $log->plot_id = $data['plot_id'];
        $log->author_id = $request->user()->id;
        $log->type = $data['type'];
        // G3：标题可选；为空时按「类型 · 地块码 · 日期」自动生成，家人只需 选类型→拍照→提交
        $title = trim((string) ($data['title'] ?? ''));
        $log->title = $title ?: $this->buildDefaultTitle($data['type'], $data['plot_id'], $data['occurred_at'] ?? now());
        $log->content = $data['content'] ?? '';
        $log->images = $images;
        $log->video_url = $videoUrl;
        // G6：露脸解说记录时长（≤60s 校验，payload 存 stage/duration）
        $log->payload = $data['type'] === FarmLogType::Explain->value
            ? ['stage' => $this->stageFor($data['occurred_at'] ?? now()), 'duration' => (int) ($data['video_duration'] ?? 0)]
            : null;
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

        // 2.7：直播预告 / 内容动态 / 露脸解说（公开）→ 推送云乡民（queue 消费，mock 只落库）
        if ($log->is_public && in_array($log->type->value, ['live_broadcast', 'daily', 'explain'], true)) {
            SendFarmLogNoticeJob::dispatch($log->id);
        }

        $label = FarmLogType::from($data['type'])->label();

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', $label.'已发布');
    }

    /** G3：标题自动生成 ——「类型 · 地块码 · 日期」。occurred_at 可能为 'Y-m-d' 字符串或 Carbon。 */
    private function buildDefaultTitle(string $type, int $plotId, \Carbon\CarbonInterface|\Carbon\Carbon|string $occurredAt): string
    {
        $plot = Plot::find($plotId);
        $date = $occurredAt instanceof \DateTimeInterface ? $occurredAt : \Carbon\Carbon::parse($occurredAt);
        return FarmLogType::from($type)->label()
            . ' · ' . ($plot ? $plot->code : '田块')
            . ' · ' . $date->format('m/d');
    }

    /** G6：当前物候阶段名（录入解说时记录到 payload.stage）。 */
    private function stageFor(\Carbon\CarbonInterface|\Carbon\Carbon|string $occurredAt): string
    {
        $date = $occurredAt instanceof \DateTimeInterface ? $occurredAt : \Carbon\Carbon::parse($occurredAt);
        return (string) (config('goji.stages')[(int) $date->format('n')]['label'] ?? '生长中');
    }

    /** G8：编辑入口（复用 create 视图）。仅作者本人或 tenant_admin 可改。 */
    public function edit(Tenant $tenant, FarmLog $farmLog, Request $request)
    {
        $this->assertScope($request, 'farm_log');
        abort_if($farmLog->author_id !== $request->user()->id && $request->user()->role->value !== 'tenant_admin', 404);
        abort_if($farmLog->tenant_id !== $tenant->id, 404);

        $plots = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')->orderBy('code')->get();
        $types = collect(FarmLogType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all();
        $batches = FertilizerBatch::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('batch_no')
            ->get(['id', 'batch_no', 'produced_at']);

        return view('family.farm_log.create', compact('tenant', 'plots', 'types', 'batches', 'farmLog'));
    }

    /** G8：更新农事动态（作者/tenant_admin）。 */
    public function update(Tenant $tenant, FarmLog $farmLog, Request $request)
    {
        $member = $this->assertScope($request, 'farm_log');
        abort_if($farmLog->author_id !== $request->user()->id && $request->user()->role->value !== 'tenant_admin', 404);
        abort_if($farmLog->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'plot_id' => ['required', Rule::exists('plots', 'id')->where('tenant_id', $tenant->id)],
            'type' => ['required', Rule::enum(FarmLogType::class)],
            'fertilizer_batch_id' => ['nullable', 'integer', Rule::exists('fertilizer_batches', 'id')->where('tenant_id', $tenant->id)],
            'title' => ['nullable', 'string', 'max:60'],
            'content' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
            'is_public' => ['boolean'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video_url' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:40960'],
            'video_duration' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $images = $farmLog->images ?? [];
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('farm-logs', 'public');
            }
        }

        $videoUrl = $request->hasFile('video_url') ? $request->file('video_url')->store('farm-logs', 'public') : $farmLog->video_url;

        $title = trim((string) ($data['title'] ?? ''));

        $farmLog->update([
            'plot_id' => $data['plot_id'],
            'type' => $data['type'],
            'title' => $title ?: $this->buildDefaultTitle($data['type'], $data['plot_id'], $data['occurred_at'] ?? $farmLog->occurred_at),
            'content' => $data['content'] ?? '',
            'images' => $images,
            'video_url' => $videoUrl,
            'payload' => $data['type'] === FarmLogType::Explain->value
                ? ['stage' => $this->stageFor($data['occurred_at'] ?? $farmLog->occurred_at), 'duration' => (int) ($data['video_duration'] ?? 0)]
                : null,
            'occurred_at' => $data['occurred_at'] ?? $farmLog->occurred_at,
            'is_public' => $request->boolean('is_public'),
            'is_trace_node' => in_array($data['type'], ['fertilize', 'harvest', 'inspect'], true),
            'fertilizer_batch_id' => $data['type'] === FarmLogType::Fertilize->value
                ? ($data['fertilizer_batch_id'] ?? null)
                : null,
        ]);

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', '已更新');
    }
}
