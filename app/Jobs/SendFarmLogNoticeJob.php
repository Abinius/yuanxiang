<?php

namespace App\Jobs;

use App\Enums\AdoptionStatus;
use App\Enums\FarmLogType;
use App\Models\Adoption;
use App\Models\FarmLog;
use App\Models\User;
use App\Services\WechatTemplateService;
use App\Tenancy\HandlesTenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 2.7 直播预告/内容动态推送：家人发 live_broadcast / daily（is_public）
 * → 推送本租户全部 active 认养人（去重、openid 非空）。
 * mock 模式下只落 push_messages 记录，P6 后真发不改码。
 */
class SendFarmLogNoticeJob implements ShouldQueue
{
    use Queueable, HandlesTenantContext;

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

        $this->withTenantContext($log->tenant_id, function () use ($log, $templates, $templateKey) {
            $url = route('tenant.home', ['tenant' => $log->tenant->slug]);

            $userIds = Adoption::query()
                ->where('status', AdoptionStatus::Active)
                ->pluck('user_id')
                ->unique();

            $recipients = User::query()
                ->whereIn('id', $userIds)
                ->whereNotNull('openid')
                ->get();

            foreach ($recipients as $user) {
                $templates->send($user, $templateKey, [
                    'url' => $url,
                    'data' => [
                        'thing1' => ['value' => mb_substr($log->title, 0, 20)],
                        'thing2' => ['value' => mb_substr($log->content ?? '看田地动态', 0, 20)],
                    ],
                ]);
            }
        });
    }
}
