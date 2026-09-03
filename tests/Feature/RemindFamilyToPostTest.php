<?php

namespace Tests\Feature;

use App\Console\Commands\RemindFamilyToPost;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\FarmMember;
use App\Models\PushMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * F4 R4.2：每日调度 family:remind-post —— 3 天未录农事的农场家人收到"该发动态了"。
 */
class RemindFamilyToPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    /** 家人角色（family_log scope），带回显 openid（mock 通道需 openid 才落 push_messages）。 */
    private function familyWithOpenid(string $phone, ?string $openid = 'mock_family_remind'): User
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '阿叔',
            'role' => 'family',
            'openid' => $openid,
        ]);

        return $user;
    }

    private function joinFarm(User $user, Farm $farm, array $scope = ['farm_log'], string $relation = 'father'): void
    {
        FarmMember::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'relation' => $relation,
            'permission_scope' => $scope,
        ]);
    }

    /** 跑命令，重置 TenantContext（防止命令污染后续用例）。 */
    private int $lastExit = 0;

    private function runCommand(): void
    {
        try {
            $this->lastExit = (int) Artisan::call('family:remind-post');
        } finally {
            TenantContext::reset();
        }
    }

    /** 静默农场（无 FarmLog）的家人收到提醒。 */
    public function test_remind_sends_to_silent_farm_members(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $farm = $t->farms()->firstOrFail();
        $member = $this->familyWithOpenid('13900000001');
        $this->joinFarm($member, $farm);

        $this->runCommand();

        $this->assertSame(0, $this->lastExit);
        $this->assertSame(1, PushMessage::where('user_id', $member->id)->count());
        $msg = PushMessage::where('user_id', $member->id)->first();
        $this->assertSame('content', $msg->type);
        $this->assertSame('sent', $msg->status);
    }

    /** 有新鲜（≤3 天）农事的农场家人不收到提醒。 */
    public function test_remind_skips_farm_with_fresh_logs(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $farm = $t->farms()->firstOrFail();
        $member = $this->familyWithOpenid('13900000002');
        $this->joinFarm($member, $farm);

        // 当日有农事
        $plot = $farm->plots()->where('type', 'plot')->firstOrFail();
        FarmLog::create([
            'tenant_id' => $t->id,
            'farm_id' => $farm->id,
            'plot_id' => $plot->id,
            'type' => 'daily',
            'title' => '新鲜',
            'content' => 'record',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        $this->runCommand();

        $this->assertSame(0, $this->lastExit);
        $this->assertSame(0, PushMessage::where('user_id', $member->id)->count());
    }

    /** 家中人没有 openid 时不推送（mock 通道跳过无 openid 用户）。 */
    public function test_remind_skips_members_without_openid(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $farm = $t->farms()->firstOrFail();
        $member = $this->familyWithOpenid('13900000003', openid: null);
        $this->joinFarm($member, $farm);

        $this->runCommand();

        $this->assertSame(0, $this->lastExit);
        $this->assertSame(0, PushMessage::count());
    }
}