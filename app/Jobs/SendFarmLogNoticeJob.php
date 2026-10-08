<?php

namespace App\Jobs;

use App\Enums\AdoptionStatus;
use App\Enums\FarmLogType;
use App\Models\Adoption;
use App\Models\FarmLog;
use App\Services\WechatTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 2.7 直播预告/内容动态推送：家人发 live_broadcast / daily（is_public）
 * → 推送全部 active 认养人（去重、openid 非空）。
 * mock 模式下只落 push_messages 记录，P6 后真发不改码。
 */
class SendFarmLogNoticeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $farmLogId)
    {
    }

    public function handle(WechatTemplateService $templates): void
    {
        $log = FarmLog::find($this->farmLogId);
        if (! $log || ! $log->is_public) {
            return;
        }
        if (! in_array($log->type->value, ['live_broadcast', 'daily', 'explain'], true)) {
            return;
        }

        $templateKey = $log->type === FarmLogType::LiveBroadcast ? 'live_notice' : 'content';

        $userIds = Adoption::query()
            ->where('status', AdoptionStatus::Active)
            ->pluck('user_id')
            ->unique()
            ->all();

        $templates->sendToAdopters($userIds, $templateKey, [
            'url' => route('tenant.home'),
            'data' => [
                'thing1' => ['value' => mb_substr($log->title, 0, 20)],
                'thing2' => ['value' => mb_substr($log->content ?? '看田地动态', 0, 20)],
            ],
        ]);
    }
}
