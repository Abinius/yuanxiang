<?php

namespace App\Jobs;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Models\Harvest;
use App\Models\Plot;
use App\Services\WechatTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 3.1 采收通知：采了某 plot → 通知该 plot 的 active 认养人「你的田今天采了」。
 */
class SendHarvestNoticeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $harvestId)
    {
    }

    public function handle(WechatTemplateService $templates): void
    {
        $harvest = Harvest::find($this->harvestId);
        if (! $harvest) {
            return;
        }

        $userIds = Adoption::query()
            ->where('adoptable_type', Plot::class)
            ->where('adoptable_id', $harvest->plot_id)
            ->where('status', AdoptionStatus::Active)
            ->pluck('user_id')
            ->unique()
            ->all();

        $templates->sendToAdopters($userIds, 'harvest_notice', [
            'url' => route('tenant.home'),
            'data' => [
                'thing1' => ['value' => $harvest->plot?->code ?? '你的田'],
                'thing2' => ['value' => mb_substr($harvest->notes ?? '今天采了，正在打单配送', 0, 20)],
            ],
        ]);
    }
}
