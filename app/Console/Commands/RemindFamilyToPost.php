<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\FarmMember;
use App\Services\WechatTemplateService;
use App\Tenancy\HandlesTenantContext;
use Illuminate\Console\Command;

/**
 * F4 R4.2：每日调度，推送"该发动态了"给 3 天未录农事的家人们。
 * 喂入真内容，避免系统节点长期占位。mock 通道只落 push_messages。
 */
class RemindFamilyToPost extends Command
{
    use HandlesTenantContext;

    protected $signature = 'family:remind-post';

    protected $description = '每日推送该发动态提醒给 3 天未录农事的家人们';

    public function __construct(
        private readonly WechatTemplateService $templates,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $recentCutoff = now()->subDays(3);

        // 各 farm 最近 3 天内的农事记录数；0 或不存在 = 该农场断更
        $recentCounts = FarmLog::query()
            ->select('farm_id', FarmLog::raw('count(*) as recent'))
            ->where('occurred_at', '>=', $recentCutoff)
            ->groupBy('farm_id')
            ->pluck('recent', 'farm_id');

        $staleFarms = Farm::query()->get()->filter(function (Farm $farm) use ($recentCounts) {
            return ($recentCounts->get($farm->id) ?? 0) < 1;
        });

        $sent = 0;

        foreach ($staleFarms as $farm) {
            $this->withTenantContext($farm->tenant_id, function () use ($farm, &$sent) {
                $members = FarmMember::query()
                    ->where('farm_id', $farm->id)
                    ->with('user')
                    ->get();

                foreach ($members as $member) {
                    $user = $member->user;
                    if (! $user || ! $user->openid) {
                        continue;
                    }

                    $this->templates->send($user, 'content', [
                        'url' => route('tenant.family.dashboard', ['tenant' => $farm->tenant->slug]),
                        'data' => [
                            'thing1' => ['value' => '记得发动态'],
                            'thing2' => ['value' => '你的家人有几天没更新了，来记录一下田间近况吧。'],
                        ],
                    ]);

                    $sent++;
                }
            });
        }

        $this->info("已提醒 {$sent} 位家人发动态（{$staleFarms->count()} 个农场地块静默）");

        return self::SUCCESS;
    }
}