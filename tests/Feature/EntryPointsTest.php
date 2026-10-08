<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 入口可达性回归：已有页面必须有入口。
 * 曾发现后台「地块管理 / 统一发货台 / 佣金审核」、后台手动建单、
 * 家人端三录入编辑页、C 端会员等级页都只能靠手输 URL 打开。
 */
class EntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function seedAll(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function plot(): Plot
    {
        return Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->firstOrFail();
    }

    public function test_admin_menu_links_plots_shipments_and_commissions(): void
    {
        $this->seedAll();
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('地块管理')
            ->assertSee('统一发货台')
            ->assertSee('佣金审核');
    }

    public function test_adoptions_index_links_manual_create_page(): void
    {
        $this->seedAll();
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/adoptions")
            ->assertOk()
            ->assertSee('手动建单');

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/adoptions/create")
            ->assertOk();
    }

    public function test_my_page_links_member_level_page(): void
    {
        $this->seedAll();
        $t = $this->tenant();
        $villager = User::create([
            'tenant_id' => $t->id, 'phone' => '13800000007', 'password' => 'secret123',
            'nickname' => '云乡民', 'role' => 'villager',
        ]);

        $this->actingAs($villager)
            ->get("/t/{$t->slug}/my")
            ->assertOk()
            ->assertSee('会员等级');

        $this->actingAs($villager)
            ->get("/t/{$t->slug}/my/member")
            ->assertOk();
    }

    public function test_family_dashboard_links_edit_pages_of_own_records(): void
    {
        $this->seedAll();
        $t = $this->tenant();
        $admin = $this->admin();

        // tenant_admin 对三类录入都有编辑权（author/handler 即本人）
        $log = new FarmLog();
        $log->tenant_id = $t->id;
        $log->farm_id = $this->farm()->id;
        $log->plot_id = $this->plot()->id;
        $log->author_id = $admin->id;
        $log->type = 'daily';
        $log->title = '入口测试动态';
        $log->occurred_at = now();
        $log->is_public = true;
        $log->save();

        $batch = new FertilizerBatch();
        $batch->tenant_id = $t->id;
        $batch->farm_id = $this->farm()->id;
        $batch->batch_no = 'NXLB-TEST-1';
        $batch->produced_at = now()->toDateString();
        $batch->save();

        $harvest = new Harvest();
        $harvest->tenant_id = $t->id;
        $harvest->farm_id = $this->farm()->id;
        $harvest->plot_id = $this->plot()->id;
        $harvest->season_year = (int) now()->year;
        $harvest->harvested_at = now()->toDateString();
        $harvest->dry_weight_kg = 12.5;
        $harvest->handler_id = $admin->id;
        $harvest->save();

        $this->actingAs($admin)
            ->get("/t/{$t->slug}/family")
            ->assertOk()
            ->assertSee('入口测试动态');

        // 三个编辑页确实可达（不再只存在于路由表里）
        $this->actingAs($admin)->get("/t/{$t->slug}/family/logs/{$log->id}/edit")->assertOk();
        $this->actingAs($admin)->get("/t/{$t->slug}/family/fertilizer/{$batch->id}/edit")->assertOk();
        $this->actingAs($admin)->get("/t/{$t->slug}/family/harvest/{$harvest->id}/edit")->assertOk();
    }
}
