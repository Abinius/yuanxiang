<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function makeTenant(string $slug = 'guangcai'): Tenant
    {
        return Tenant::create(['slug' => $slug, 'name' => '光彩云村庄', 'status' => 'active']);
    }

    private function user(Tenant $tenant, UserRole $role, string $phone = '13800000001'): User
    {
        return User::create([
            'tenant_id' => $role === UserRole::PlatformAdmin ? null : $tenant->id,
            'phone' => $phone,
            'username' => $phone,
            'password' => 'secret123',
            'nickname' => '测试用户',
            'role' => $role->value,
        ]);
    }

    public function test_platform_login_page_and_flow(): void
    {
        $t = $this->makeTenant();
        $this->user($t, UserRole::PlatformAdmin, '13900000000');

        $this->get('/platform/login')->assertOk();
        $this->post('/platform/login', ['account' => '13900000000', 'password' => 'secret123'])
            ->assertRedirect('/platform');
        $this->assertAuthenticated();
    }

    public function test_platform_dashboard_lists_tenants(): void
    {
        $t = $this->makeTenant();
        $admin = $this->user($t, UserRole::PlatformAdmin, '13900000000');

        $this->actingAs($admin)
            ->get('/platform')
            ->assertOk()
            ->assertSee('光彩云村庄');
    }

    public function test_villager_cannot_access_platform(): void
    {
        $t = $this->makeTenant();
        $this->actingAs($this->user($t, UserRole::Villager))
            ->get('/platform')
            ->assertForbidden();
    }

    public function test_guest_redirected_to_tenant_login_on_admin(): void
    {
        $t = $this->makeTenant();
        $this->get("/t/{$t->slug}/admin")->assertRedirect("/t/{$t->slug}/login");
    }

    public function test_tenant_admin_can_access_own_dashboard(): void
    {
        $t = $this->makeTenant();
        $this->actingAs($this->user($t, UserRole::TenantAdmin))
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('商户后台');
    }

    public function test_tenant_admin_cannot_access_other_tenant_dashboard(): void
    {
        $t1 = $this->makeTenant();
        $t2 = $this->makeTenant('other');
        $this->actingAs($this->user($t1, UserRole::TenantAdmin))
            ->get("/t/{$t2->slug}/admin")
            ->assertForbidden();
    }

    public function test_family_cannot_access_admin_dashboard(): void
    {
        $t = $this->makeTenant();
        $this->actingAs($this->user($t, UserRole::Family))
            ->get("/t/{$t->slug}/admin")
            ->assertForbidden();
    }

    public function test_family_can_access_family_dashboard(): void
    {
        $t = $this->makeTenant();
        $this->actingAs($this->user($t, UserRole::Family))
            ->get("/t/{$t->slug}/family")
            ->assertOk()
            ->assertSee('家人录入端');
    }
}
