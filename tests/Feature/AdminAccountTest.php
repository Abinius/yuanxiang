<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 1.6 后台账号体系：商户/平台后台账号管理 CRUD + 禁用 + 登录拦截。
 */
class AdminAccountTest extends TestCase
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

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function platformAdmin(): User
    {
        return User::where('username', 'platform')->firstOrFail();
    }

    // ---------- 商户后台账号管理 ----------

    public function test_tenant_admin_can_list_users(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/users")
            ->assertOk()
            ->assertSee('账号管理');
    }

    public function test_tenant_admin_can_create_family_user(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/users", [
                'nickname' => '王家人',
                'phone' => '13700000001',
                'username' => 'wangfamily',
                'role' => UserRole::Family->value,
                'password' => 'secret123',
            ])
            ->assertRedirect();

        $user = User::where('phone', '13700000001')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Family->value, $user->role->value);
        $this->assertSame($t->id, $user->tenant_id);
    }

    public function test_tenant_admin_cannot_create_platform_admin(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        // role 字段白名单不含 platform_admin，校验失败
        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/users", [
                'nickname' => '越权',
                'phone' => '13700000002',
                'role' => UserRole::PlatformAdmin->value,
                'password' => 'secret123',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_tenant_admin_can_disable_and_login_blocked(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $target = User::create([
            'tenant_id' => $t->id, 'phone' => '13700000003', 'password' => 'secret123',
            'nickname' => '待禁', 'role' => UserRole::Villager->value,
        ]);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/users/{$target->id}/toggle")
            ->assertRedirect();
        $this->assertTrue((bool) $target->fresh()->is_disabled);

        // 禁用后登录被拦截
        $this->post("/t/{$t->slug}/login", [
            'account' => '13700000003', 'password' => 'secret123',
        ])->assertSessionHasErrors(['account']);
    }

    public function test_tenant_admin_cannot_disable_self(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $me = $this->admin();

        $this->actingAs($me)
            ->post("/t/{$t->slug}/admin/users/{$me->id}/toggle")
            ->assertStatus(422);
    }

    public function test_tenant_admin_can_reset_password(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $target = User::create([
            'tenant_id' => $t->id, 'phone' => '13700000004', 'password' => 'secret123',
            'nickname' => '改密', 'role' => UserRole::Villager->value,
        ]);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/users/{$target->id}/reset-password", [
                'password' => 'newpass123',
            ])
            ->assertRedirect();

        // 新密码可登录
        $this->post("/t/{$t->slug}/login", [
            'account' => '13700000004', 'password' => 'newpass123',
        ])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_tenant_admin_cannot_manage_other_tenant_user(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);

        $this->actingAs($this->admin())
            ->get("/t/{$other->slug}/admin/users")
            ->assertForbidden(); // 跨租户，RoleMiddleware 拦截
    }

    public function test_villager_cannot_access_admin_users(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $villager = User::create([
            'tenant_id' => $t->id, 'phone' => '13700000006', 'password' => 'secret123',
            'nickname' => '村民', 'role' => UserRole::Villager->value,
        ]);

        $this->actingAs($villager)
            ->get("/t/{$t->slug}/admin/users")
            ->assertForbidden();
    }

    // ---------- 平台后台账号管理 ----------

    public function test_platform_admin_can_list_all_users(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);

        $this->actingAs($this->platformAdmin())
            ->get('/platform/users')
            ->assertOk()
            ->assertSee('平台账号管理');
    }

    public function test_platform_admin_can_create_tenant_admin(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->platformAdmin())
            ->post('/platform/users', [
                'nickname' => '新商户管理员',
                'phone' => '13900000001',
                'role' => UserRole::TenantAdmin->value,
                'tenant_id' => $t->id,
                'password' => 'secret123',
            ])
            ->assertRedirect();

        $user = User::where('phone', '13900000001')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::TenantAdmin->value, $user->role->value);
        $this->assertSame($t->id, $user->tenant_id);
    }

    public function test_platform_admin_can_create_platform_admin(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);

        $this->actingAs($this->platformAdmin())
            ->post('/platform/users', [
                'nickname' => '新平台管理员',
                'phone' => '13900000002',
                'role' => UserRole::PlatformAdmin->value,
                'password' => 'secret123',
            ])
            ->assertRedirect();

        $user = User::where('phone', '13900000002')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::PlatformAdmin->value, $user->role->value);
        $this->assertNull($user->tenant_id);
    }

    public function test_tenant_admin_cannot_access_platform_users(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);

        $this->actingAs($this->admin())
            ->get('/platform/users')
            ->assertForbidden();
    }
}
