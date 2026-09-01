<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Plot;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset(); // 避免静态上下文在用例间泄漏
    }

    private function makeTenant(string $slug = 'guangcai', string $name = '光彩云村庄', string $status = 'active'): Tenant
    {
        return Tenant::create(['slug' => $slug, 'name' => $name, 'status' => $status]);
    }

    public function test_health_route(): void
    {
        $this->get('/up')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_tenant_home_returns_200_and_shows_name(): void
    {
        $this->makeTenant();
        $this->get('/t/guangcai')
            ->assertOk()
            ->assertSee('光彩云村庄');
    }

    public function test_missing_tenant_returns_404(): void
    {
        $this->get('/t/nonexistent')->assertNotFound();
    }

    public function test_suspended_tenant_returns_403(): void
    {
        $this->makeTenant('suspended', '停用村庄', 'suspended');
        $this->get('/t/suspended')->assertForbidden();
    }

    public function test_tenant_scope_filters_by_current_context(): void
    {
        $t1 = $this->makeTenant();
        $t2 = $this->makeTenant('other', '别的村');
        Farm::create(['tenant_id' => $t1->id, 'name' => '光彩村基地']);
        Farm::create(['tenant_id' => $t2->id, 'name' => '别的基地']);

        $this->assertSame(2, Farm::count()); // 无上下文：全量可见（平台语境）

        TenantContext::set($t1->id);
        $this->assertSame(1, Farm::count());
        $this->assertSame('光彩村基地', Farm::first()->name);
        TenantContext::reset();
    }

    public function test_write_without_tenant_context_is_blocked(): void
    {
        $this->expectException(\RuntimeException::class);
        Plot::create(['farm_id' => 1, 'type' => 'plot', 'code' => 'X-01']);
    }

    public function test_write_in_context_auto_injects_tenant_id(): void
    {
        $t1 = $this->makeTenant();
        TenantContext::set($t1->id);
        $farm = Farm::create(['name' => '自动注入基地']); // 不传 tenant_id
        $this->assertSame($t1->id, $farm->tenant_id);
        TenantContext::reset();
    }

    public function test_plot_unique_code_per_tenant(): void
    {
        $t1 = $this->makeTenant();
        $t2 = $this->makeTenant('other');
        $f1 = Farm::create(['tenant_id' => $t1->id, 'name' => '基地A']);
        $f2 = Farm::create(['tenant_id' => $t2->id, 'name' => '基地B']);

        Plot::create(['tenant_id' => $t1->id, 'farm_id' => $f1->id, 'type' => 'plot', 'code' => 'FD-A01']);
        // 不同租户可同码
        Plot::create(['tenant_id' => $t2->id, 'farm_id' => $f2->id, 'type' => 'plot', 'code' => 'FD-A01']);
        $this->assertSame(2, Plot::count());

        // 同租户同码 → 唯一约束报错
        $this->expectException(\Illuminate\Database\QueryException::class);
        Plot::create(['tenant_id' => $t1->id, 'farm_id' => $f1->id, 'type' => 'plot', 'code' => 'FD-A01']);
    }
}
