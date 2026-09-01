<?php

namespace Tests\Feature;

use App\Models\Camera;
use App\Models\Farm;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\CameraSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2.3 监控嵌入：/live 播放器（HLS mock）+ 断流降级 + 后台摄像头管理。
 * P3 摄像头未到位 → 用公开 HLS 测试流 mock，真实流填 stream_url 即上线不改码。
 */
class LiveTest extends TestCase
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

    private function villager(string $phone = '13800000001'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, CameraSeeder::class]);
        $t = $this->tenant();

        $this->get("/t/{$t->slug}/live")
            ->assertRedirect("/t/{$t->slug}/login");
    }

    public function test_villager_sees_camera_list_with_status_tags(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, CameraSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->villager())
            ->get("/t/{$t->slug}/live")
            ->assertOk()
            ->assertSee('FD-01 田块摄像头')
            ->assertSee('FD-02 田块摄像头')
            ->assertSee('在线')
            ->assertSee('离线');
    }

    public function test_online_camera_renders_hls_player_with_stream_url(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, CameraSeeder::class]);
        $t = $this->tenant();
        $online = Camera::where('device_no', 'EZ-MOCK-0001')->firstOrFail();

        $this->actingAs($this->villager())
            ->get("/t/{$t->slug}/live/{$online->id}")
            ->assertOk()
            ->assertSee('<video id="live-video"', false)
            ->assertSee('test-streams.mux.dev');
    }

    public function test_offline_camera_renders_degrade_panel(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, CameraSeeder::class]);
        $t = $this->tenant();
        $offline = Camera::where('device_no', 'EZ-MOCK-0002')->firstOrFail();

        $this->actingAs($this->villager())
            ->get("/t/{$t->slug}/live/{$offline->id}")
            ->assertOk()
            ->assertSee('摄像头离线')
            ->assertDontSee('live-video');
    }

    public function test_cross_tenant_camera_is_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $other = Tenant::create(['slug' => 'other', 'name' => '他租户', 'status' => 'active']);
        $otherFarm = Farm::create(['tenant_id' => $other->id, 'name' => '他基地']);
        $otherCam = Camera::create([
            'tenant_id' => $other->id,
            'farm_id' => $otherFarm->id,
            'name' => '他摄像头',
            'device_no' => 'OTH-001',
            'status' => 'online',
        ]);

        $this->actingAs($this->villager())
            ->get("/t/{$t->slug}/live/{$otherCam->id}")
            ->assertNotFound();
    }

    public function test_other_tenant_user_cannot_view_cameras(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, CameraSeeder::class]);
        $t = $this->tenant();
        $camera = Camera::where('device_no', 'EZ-MOCK-0001')->firstOrFail();

        // 他租户已登录用户（P0：tenant.member 须为租户成员，仅 auth 不够）
        $other = Tenant::create(['slug' => 'other', 'name' => '他租户', 'status' => 'active']);
        $otherUser = User::create([
            'tenant_id' => $other->id,
            'phone' => '13800000099',
            'password' => 'secret123',
            'nickname' => '他租户用户',
            'role' => 'villager',
        ]);

        $this->actingAs($otherUser)
            ->get("/t/{$t->slug}/live")
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->get("/t/{$t->slug}/live/{$camera->id}")
            ->assertForbidden();
    }

    public function test_admin_manages_cameras_villager_is_forbidden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/t/{$t->slug}/admin/cameras", [
                'name' => '新增摄像头',
                'device_no' => 'EZ-NEW-001',
                'provider' => 'ezviz',
                'status' => 'online',
            ])
            ->assertRedirect();

        $created = Camera::where('device_no', 'EZ-NEW-001')->firstOrFail();
        $this->assertEquals($t->id, $created->tenant_id);

        // villager（role 错）→ 403
        $this->actingAs($this->villager('13800000002'))
            ->post("/t/{$t->slug}/admin/cameras", [
                'name' => '越权',
                'device_no' => 'EZ-HACK',
                'status' => 'offline',
            ])
            ->assertForbidden();
    }
}
