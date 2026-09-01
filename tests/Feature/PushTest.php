<?php

namespace Tests\Feature;

use App\Enums\FarmLogType;
use App\Jobs\SendFarmLogNoticeJob;
use App\Models\Adoption;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\FarmMember;
use App\Models\Plot;
use App\Models\PushMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\WechatTemplateService;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 2.7 内容/直播预告推送：live_broadcast + daily 公开动态 → 推本租户 active 认养人（openid 非空）。
 * mock 模式只落 push_messages 记录；跨租户隔离。
 */
class PushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private int $phoneCounter = 0;

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function plot(): Plot
    {
        return Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->first();
    }

    private function orderData(): array
    {
        return [
            'name' => '张三',
            'phone' => '13800000001',
            'province' => '宁夏',
            'city' => '吴忠',
            'district' => '红寺堡',
            'detail' => '光彩村 1 号',
        ];
    }

    /** 建 family 用户 + farm_member（farm_log scope）。 */
    private function familyUser(): User
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000001',
            'password' => 'secret123',
            'nickname' => '阿叔',
            'role' => 'family',
        ]);
        FarmMember::create([
            'tenant_id' => $t->id,
            'user_id' => $user->id,
            'farm_id' => $this->farm()->id,
            'relation' => 'father',
            'permission_scope' => ['farm_log'],
        ]);

        return $user;
    }

    /** 建 active 认养人（openid 可空）。 */
    private function makeActiveAdopter(?string $openid): User
    {
        $this->phoneCounter++;
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '1381'.str_pad((string) $this->phoneCounter, 7, '0', STR_PAD_LEFT),
            'openid' => $openid,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);

        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $t->id)
            ->where('type', 'plot')
            ->where('status', 'available')
            ->orderBy('id')
            ->firstOrFail();
        $adoption = $service->createOrder($user, $plot, $this->orderData());
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '云乡民的田');

        return $user;
    }

    private function makeLog(FarmLogType $type, string $title, bool $public = true): FarmLog
    {
        return FarmLog::create([
            'tenant_id' => $this->tenant()->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $this->plot()->id,
            'type' => $type->value,
            'title' => $title,
            'content' => '推送内容',
            'occurred_at' => now(),
            'is_public' => $public,
            'source' => 'family',
        ]);
    }

    public function test_live_broadcast_dispatches_notice_job(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser();
        Queue::fake();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $this->plot()->id,
                'type' => 'live_broadcast',
                'title' => '今晚八点开播',
                'is_public' => '1',
            ])
            ->assertRedirect();

        Queue::assertPushed(SendFarmLogNoticeJob::class);
    }

    public function test_job_sends_to_active_adopters_with_openid_in_mock(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $withOpenid = $this->makeActiveAdopter('mock_push_1');
        $this->makeActiveAdopter(null); // 无 openid，不收

        $log = $this->makeLog(FarmLogType::LiveBroadcast, '开播预告');
        (new SendFarmLogNoticeJob($log->id))->handle(app(WechatTemplateService::class));

        $this->assertSame(1, PushMessage::count());
        $message = PushMessage::first();
        $this->assertEquals($withOpenid->id, $message->user_id);
        $this->assertSame('sent', $message->status);
        $this->assertSame('live_notice', $message->type);
    }

    public function test_daily_content_pushes_but_fertilize_does_not(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->makeActiveAdopter('mock_push_1');

        $daily = $this->makeLog(FarmLogType::Daily, '新动态');
        (new SendFarmLogNoticeJob($daily->id))->handle(app(WechatTemplateService::class));
        $this->assertSame('content', PushMessage::latest('id')->first()->type);

        $fert = $this->makeLog(FarmLogType::Fertilize, '施肥');
        (new SendFarmLogNoticeJob($fert->id))->handle(app(WechatTemplateService::class));
        $this->assertSame(1, PushMessage::count());
    }

    public function test_non_public_log_does_not_push(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->makeActiveAdopter('mock_push_1');

        $log = $this->makeLog(FarmLogType::LiveBroadcast, '私密预告', false);
        (new SendFarmLogNoticeJob($log->id))->handle(app(WechatTemplateService::class));

        $this->assertSame(0, PushMessage::count());
    }

    public function test_other_tenant_user_with_openid_not_receives(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $gcUser = $this->makeActiveAdopter('mock_gc_1');

        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);
        User::create([
            'tenant_id' => $other->id,
            'phone' => '13820000001',
            'openid' => 'mock_other_1',
            'nickname' => '别村人',
            'role' => 'villager',
        ]);

        $log = $this->makeLog(FarmLogType::LiveBroadcast, '开播预告');
        (new SendFarmLogNoticeJob($log->id))->handle(app(WechatTemplateService::class));

        $this->assertSame(1, PushMessage::count());
        $this->assertEquals($gcUser->id, PushMessage::first()->user_id);
    }
}
