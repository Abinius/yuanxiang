<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Farm;
use App\Models\FarmMember;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
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
            ->get("/admin/users")
            ->assertOk()
            ->assertSee('账号管理');
    }

    public function test_tenant_admin_can_create_family_user(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->post("/admin/users", [
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

    public function test_tenant_admin_creates_family_user_with_scopes_syncs_membership(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/admin/users", [
                'nickname' => '王家人',
                'phone' => '13700000002',
                'role' => UserRole::Family->value,
                'password' => 'secret123',
                'relation' => 'mother',
                'permission_scope' => ['farm_log', 'plot'],
            ])
            ->assertRedirect();

        $user = User::where('phone', '13700000002')->first();
        $member = $user->farmMemberships->first();
        $this->assertSame($farm->id, $member->farm_id);
        $this->assertSame('mother', $member->relation);
        $this->assertSame(['farm_log', 'plot'], $member->permission_scope);

        // 配了 plot：家人端地块页打得开（过去这一步是 403）
        $this->actingAs($user)->get("/family/plots")->assertOk();
    }

    public function test_tenant_admin_can_update_family_scopes(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);

        $this->actingAs($this->admin())
            ->post("/admin/users", [
                'nickname' => '李家人', 'phone' => '13700000003',
                'role' => UserRole::Family->value, 'password' => 'secret123',
            ])
            ->assertRedirect();

        $user = User::where('phone', '13700000003')->first();
        $this->assertSame([], $user->farmMemberships->first()->permission_scope);
        $this->actingAs($user)->get("/family/plots")->assertForbidden();

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}", [
                'nickname' => '李家人', 'phone' => '13700000003',
                'role' => UserRole::Family->value,
                'permission_scope' => ['plot'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // actingAs 期间该关联被缓存，直接从表里读最新值
        $member = FarmMember::where('user_id', $user->id)->first();
        $this->assertSame(['plot'], $member->permission_scope);
        $this->actingAs($user)->get("/family/plots")->assertOk();
    }

    public function test_tenant_admin_user_forms_render_scopes(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);

        // 新建页：权限词表来自 FamilyScope::labels()（新增录入类型只改一处）
        $this->actingAs($this->admin())
            ->get("/admin/users/create")
            ->assertOk()
            ->assertSee('录入权限')
            ->assertSee('地块录入');

        $this->actingAs($this->admin())
            ->post("/admin/users", [
                'nickname' => '表单家人', 'phone' => '13700000006',
                'role' => UserRole::Family->value, 'password' => 'secret123',
                'relation' => 'mother', 'permission_scope' => ['plot'],
            ])
            ->assertRedirect();

        // 编辑页：回填身份关系与已勾选项
        $user = User::where('phone', '13700000006')->first();
        $this->actingAs($this->admin())
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('value="mother" selected>', false)
            ->assertSee('value="plot" checked>', false);
    }

    public function test_tenant_admin_cannot_assign_unknown_scope(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);

        $this->actingAs($this->admin())
            ->post("/admin/users", [
                'nickname' => '越权家人', 'phone' => '13700000004',
                'role' => UserRole::Family->value, 'password' => 'secret123',
                'permission_scope' => ['farm_log', 'tenant_admin'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        // 带点号的数组键取不到 MessageBag，直接看错误键名
        $this->assertContains('permission_scope.1', Session::get('errors')->keys());
        $this->assertNull(User::where('phone', '13700000004')->first());
    }

    public function test_villager_account_does_not_create_farm_member(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);

        $this->actingAs($this->admin())
            ->post("/admin/users", [
                'nickname' => '村民甲', 'phone' => '13700000005',
                'role' => UserRole::Villager->value, 'password' => 'secret123',
                'permission_scope' => ['plot'],
            ])
            ->assertRedirect();

        $user = User::where('phone', '13700000005')->first();
        $this->assertEmpty($user->farmMemberships);
    }

    public function test_tenant_admin_cannot_create_platform_admin(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        // role 字段白名单不含 platform_admin，校验失败
        $this->actingAs($this->admin())
            ->post("/admin/users", [
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
            ->post("/admin/users/{$target->id}/toggle")
            ->assertRedirect();
        $this->assertTrue((bool) $target->fresh()->is_disabled);

        // 禁用后登录被拦截
        $this->post("/login", [
            'account' => '13700000003', 'password' => 'secret123', 'agreed' => '1',
        ])->assertSessionHasErrors(['account']);
    }

    public function test_tenant_admin_cannot_disable_self(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $me = $this->admin();

        $this->actingAs($me)
            ->post("/admin/users/{$me->id}/toggle")
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
            ->post("/admin/users/{$target->id}/reset-password", [
                'password' => 'newpass123',
            ])
            ->assertRedirect();

        // 新密码可登录
        $this->post("/login", [
            'account' => '13700000004', 'password' => 'newpass123', 'agreed' => '1',
        ])->assertRedirect();
        $this->assertAuthenticated();
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
            ->get("/admin/users")
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
