<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2.8 后台补齐：订单列表、内容列表（软删）、后台首页导航、退款按钮条件。
 */
class AdminOpsTest extends TestCase
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

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function plot(): Plot
    {
        return Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->first();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function villager(): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
        ]);
    }

    private function makeOrder(User $user): Adoption
    {
        $service = app(AdoptionService::class);

        return $service->createOrder($user, $this->plot(), [
            'name' => '张三',
            'phone' => '13800000001',
            'province' => '宁夏',
            'city' => '吴忠',
            'district' => '红寺堡',
            'detail' => '光彩村 1 号',
        ]);
    }

    public function test_admin_sees_orders_list_villager_forbidden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeOrder($user);

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/adoptions")
            ->assertOk()
            ->assertSee($adoption->adoption_no)
            ->assertSee('云乡民阿林')
            ->assertSee('待支付');

        $this->actingAs($user)
            ->get("/t/{$t->slug}/admin/adoptions")
            ->assertForbidden();
    }

    public function test_admin_sees_farm_logs_villager_forbidden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();

        FarmLog::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $this->plot()->id,
            'type' => 'daily',
            'title' => '今日巡田记录',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/farm-logs")
            ->assertOk()
            ->assertSee('今日巡田记录');

        $this->actingAs($user)
            ->get("/t/{$t->slug}/admin/farm-logs")
            ->assertForbidden();
    }

    public function test_admin_soft_deletes_farm_log_cross_tenant_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $log = FarmLog::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $this->plot()->id,
            'type' => 'daily',
            'title' => '待删除记录',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        $this->actingAs($this->admin())
            ->delete("/t/{$t->slug}/admin/farm-logs/{$log->id}")
            ->assertRedirect();
        $this->assertSoftDeleted('farm_logs', ['id' => $log->id]);

        // 跨租户 log → 404
        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);
        $otherFarm = Farm::create(['tenant_id' => $other->id, 'name' => '他基地']);
        $otherLog = FarmLog::create([
            'tenant_id' => $other->id,
            'farm_id' => $otherFarm->id,
            'type' => 'daily',
            'title' => '别家记录',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        $this->actingAs($this->admin())
            ->delete("/t/{$t->slug}/admin/farm-logs/{$otherLog->id}")
            ->assertNotFound();
    }

    public function test_dashboard_shows_management_links(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        // 侧边栏全菜单 + 管理端→家人端/前台 互链（Part A 导航互通）
        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('经营看板')
            ->assertSee('认养订单')
            ->assertSee('农事内容')
            ->assertSee('摄像头')
            ->assertSee('溯源码')
            ->assertSee('配送管理')
            ->assertSee('补退管理')
            ->assertSee('促销')
            ->assertSee('站点设置')
            ->assertSee('短链接')
            ->assertSee('家人端')
            ->assertSee('前台');
    }

    public function test_adoptions_status_filter_filters_list(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();

        $pending = $this->makeOrder($user); // pending_payment

        // 第二块分地建一单并生效（避免与 makeOrder 撞同一田块）
        $service = app(AdoptionService::class);
        $plot2 = Plot::where('tenant_id', $t->id)->where('type', 'plot')->orderBy('id')->skip(1)->first();
        $active = $service->createOrder($user, $plot2, [
            'name' => '张三', 'phone' => '13800000001', 'province' => '宁夏',
            'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ]);
        $service->confirmMockPayment($active);
        $service->signAgreement($active, '生效的田');

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/adoptions?status=active")
            ->assertOk()
            ->assertSee($active->adoptable->code)
            ->assertDontSee($pending->adoption_no);

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/adoptions?status=pending_payment")
            ->assertOk()
            ->assertSee($pending->adoption_no)
            ->assertDontSee($active->adoptable->code);
    }

    public function test_refund_button_only_for_paid_orders(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeOrder($user);

        // pending_payment：无退款按钮
        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/adoptions")
            ->assertOk()
            ->assertDontSee('退款');

        // 支付后：出现退款按钮
        app(AdoptionService::class)->confirmMockPayment($adoption);
        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/adoptions")
            ->assertOk()
            ->assertSee('退款');
    }
}
