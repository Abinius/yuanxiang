<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 三后台入口菜单（Part A）：C端顶部导航按角色显示我的田/实时监控/家人后台/管理后台。
 */
class NavMenuTest extends TestCase
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

    private function user(string $phone, UserRole $role): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '用户',
            'role' => $role->value,
        ]);
    }

    public function test_villager_sees_my_field_and_live_but_no_backends(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $u = $this->user('13800000001', UserRole::Villager);

        $this->actingAs($u)
            ->get("/t/{$t->slug}/")
            ->assertOk()
            ->assertSee('我的田')
            ->assertSee('实时监控')
            ->assertDontSee('家人后台')
            ->assertDontSee('管理后台');
    }

    public function test_family_sees_family_backend_not_admin(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $u = $this->user('13900000001', UserRole::Family);

        $this->actingAs($u)
            ->get("/t/{$t->slug}/")
            ->assertOk()
            ->assertSee('家人后台')
            ->assertDontSee('管理后台');
    }

    public function test_tenant_admin_sees_both_backends(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $u = $this->user('13800000099', UserRole::TenantAdmin);

        $this->actingAs($u)
            ->get("/t/{$t->slug}/")
            ->assertOk()
            ->assertSee('家人后台')
            ->assertSee('管理后台');
    }
}
