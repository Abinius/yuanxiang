<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Enums\UserRole;
use App\Models\Adoption;
use App\Models\Farm;
use App\Models\FarmMember;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 田地动态化：admin CRUD + 删除保护 + 家人端录入。
 */
class PlotManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        $this->seed(BaseSeeder::class);
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function admin(Tenant $t): User
    {
        return User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000001',
            'username' => '13900000001',
            'password' => 'secret123',
            'nickname' => '管理员',
            'role' => UserRole::TenantAdmin->value,
        ]);
    }

    private function familyMember(Tenant $t, Farm $farm): array
    {
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000002',
            'username' => '13900000002',
            'password' => 'secret123',
            'nickname' => '家人',
            'role' => UserRole::Family->value,
        ]);
        $member = FarmMember::create([
            'tenant_id' => $t->id,
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'relation' => '家人',
            'permission_scope' => ['plot', 'farm_log'],
        ]);

        return [$user, $member];
    }

    private function plotData(Farm $farm, Plan $plan, string $code = 'FD-NEW-01'): array
    {
        return [
            'farm_id' => $farm->id,
            'plan_id' => $plan->id,
            'type' => 'plot',
            'code' => $code,
            'mu_area' => 0.1,
            'price_yearly' => 5000,
            'status' => 'available',
            'order_index' => 0,
            'story' => '测试地块故事',
        ];
    }

    public function test_admin_can_create_plot(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $this->actingAs($admin)
            ->post("/t/{$t->slug}/admin/plots", $this->plotData($farm, $plan))
            ->assertRedirect(route('tenant.admin.plots.index', ['tenant' => $t->slug]));

        $this->assertDatabaseHas('plots', [
            'tenant_id' => $t->id,
            'code' => 'FD-NEW-01',
            'story' => '测试地块故事',
        ]);
    }

    public function test_admin_can_update_plot(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-UP-01'), ['tenant_id' => $t->id]));

        $this->actingAs($admin)
            ->put("/t/{$t->slug}/admin/plots/{$plot->id}", array_merge($this->plotData($farm, $plan, 'FD-UP-01'), ['story' => '改后的故事']))
            ->assertRedirect(route('tenant.admin.plots.index', ['tenant' => $t->slug]));

        $this->assertSame('改后的故事', $plot->fresh()->story);
    }

    public function test_admin_can_delete_plot_without_adoptions(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-DEL-01'), ['tenant_id' => $t->id]));

        $this->actingAs($admin)
            ->delete("/t/{$t->slug}/admin/plots/{$plot->id}")
            ->assertRedirect(route('tenant.admin.plots.index', ['tenant' => $t->slug]));

        $this->assertSoftDeleted('plots', ['id' => $plot->id]);
    }

    public function test_admin_cannot_delete_plot_with_active_adoption(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);
        $villager = User::create([
            'tenant_id' => $t->id, 'phone' => '13800000010', 'username' => '13800000010',
            'password' => 'secret123', 'nickname' => '云乡民', 'role' => UserRole::Villager->value,
        ]);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-LOCK-01'), ['tenant_id' => $t->id]));

        // 在约认养（生效中，未到期）
        Adoption::create([
            'tenant_id' => $t->id, 'user_id' => $villager->id,
            'adoption_no' => 'TEST-LOCK-1',
            'adoptable_type' => Plot::class, 'adoptable_id' => $plot->id,
            'plan_id' => $plan->id, 'farm_id' => $farm->id,
            'season_year' => 2026, 'annual_fee' => 5000,
            'start_date' => now(), 'end_date' => now()->addYear(),
            'status' => AdoptionStatus::Active->value,
        ]);

        $this->assertTrue($plot->fresh()->hasInFlightAdoptions());

        $this->actingAs($admin)
            ->delete("/t/{$t->slug}/admin/plots/{$plot->id}")
            ->assertRedirect(route('tenant.admin.plots.index', ['tenant' => $t->slug]));

        $this->assertNotSoftDeleted('plots', ['id' => $plot->id]);
    }

    public function test_admin_can_delete_plot_after_adoption_ended(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-EXP-01'), ['tenant_id' => $t->id]));

        // 已到期认养 → 不阻止删除
        Adoption::create([
            'tenant_id' => $t->id, 'user_id' => $admin->id,
            'adoption_no' => 'TEST-EXP-1',
            'adoptable_type' => Plot::class, 'adoptable_id' => $plot->id,
            'plan_id' => $plan->id, 'farm_id' => $farm->id,
            'season_year' => 2025, 'annual_fee' => 5000,
            'start_date' => now()->subYear(), 'end_date' => now()->subDay(),
            'status' => AdoptionStatus::Ended->value,
        ]);

        $this->assertFalse($plot->fresh()->hasInFlightAdoptions());

        $this->actingAs($admin)
            ->delete("/t/{$t->slug}/admin/plots/{$plot->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('plots', ['id' => $plot->id]);
    }

    public function test_family_can_create_plot_within_own_farm(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        [$user] = $this->familyMember($t, $farm);

        $data = $this->plotData($farm, $plan, 'FD-FAM-01');
        unset($data['farm_id']); // 家人表单不带 farm_id（控制器锁定）

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/plots", $data)
            ->assertRedirect(route('tenant.family.plots.index', ['tenant' => $t->slug]));

        $this->assertDatabaseHas('plots', [
            'tenant_id' => $t->id, 'code' => 'FD-FAM-01', 'farm_id' => $farm->id,
        ]);
    }

    public function test_family_cannot_edit_plot_from_other_farm(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();

        // 他基地 + 其上地块
        $otherFarm = Farm::create(['tenant_id' => $t->id, 'name' => '他基地']);
        $otherPlot = Plot::create(array_merge($this->plotData($otherFarm, $plan, 'FD-OTH-01'), ['tenant_id' => $t->id]));

        [$user] = $this->familyMember($t, $farm);

        $data = $this->plotData($otherFarm, $plan, 'FD-OTH-01');
        unset($data['farm_id']);

        $this->actingAs($user)
            ->put("/t/{$t->slug}/family/plots/{$otherPlot->id}", $data)
            ->assertForbidden();
    }

    public function test_cross_tenant_admin_edit_returns_404(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $other = Tenant::create(['slug' => 'other', 'name' => '他租户', 'status' => 'active']);
        $otherFarm = Farm::create(['tenant_id' => $other->id, 'name' => '他基地']);
        $otherPlan = Plan::create(['tenant_id' => $other->id, 'name' => '他方案', 'price_yearly' => 5000]);
        $otherPlot = Plot::create(array_merge($this->plotData($otherFarm, $otherPlan, 'FD-X-01'), ['tenant_id' => $other->id]));

        $this->actingAs($admin)
            ->put("/t/{$t->slug}/admin/plots/{$otherPlot->id}", $this->plotData($farm, $plan, 'FD-X-01'))
            ->assertNotFound();
    }

    public function test_plot_code_unique_per_tenant(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        Plot::create(array_merge($this->plotData($farm, $plan, 'FD-DUP-01'), ['tenant_id' => $t->id]));

        $this->actingAs($admin)
            ->post("/t/{$t->slug}/admin/plots", $this->plotData($farm, $plan, 'FD-DUP-01'))
            ->assertSessionHasErrors('code');
    }
}
