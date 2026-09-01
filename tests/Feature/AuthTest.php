<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
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

    public function test_login_page_renders_both_tabs(): void
    {
        $t = $this->makeTenant();
        $this->get("/t/{$t->slug}/login")
            ->assertOk()
            ->assertSee('账号登录')
            ->assertSee('微信一键登录');
    }

    public function test_password_login_succeeds_and_redirects(): void
    {
        $t = $this->makeTenant();
        User::create([
            'tenant_id' => $t->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'nickname' => '阿林',
            'role' => UserRole::Villager->value,
        ]);

        $this->post("/t/{$t->slug}/login", ['account' => '13800000001', 'password' => 'secret123'])
            ->assertRedirect("/t/{$t->slug}");
        $this->assertAuthenticated();
    }

    public function test_wrong_password_returns_form_error(): void
    {
        $t = $this->makeTenant();
        User::create([
            'tenant_id' => $t->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'role' => UserRole::Villager->value,
        ]);

        $this->post("/t/{$t->slug}/login", ['account' => '13800000001', 'password' => 'wrong'])
            ->assertSessionHasErrors('account');
        $this->assertGuest();
    }

    public function test_login_is_scoped_to_tenant(): void
    {
        $t1 = $this->makeTenant();
        $t2 = $this->makeTenant('other');
        User::create([
            'tenant_id' => $t1->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'role' => UserRole::Villager->value,
        ]);

        // 手机号属于 t1，在 t2 登录必须失败
        $this->post("/t/{$t2->slug}/login", ['account' => '13800000001', 'password' => 'secret123'])
            ->assertSessionHasErrors('account');
    }

    public function test_username_login_works(): void
    {
        $t = $this->makeTenant();
        User::create([
            'tenant_id' => $t->id,
            'username' => 'abin',
            'password' => 'secret123',
            'role' => UserRole::Villager->value,
        ]);

        $this->post("/t/{$t->slug}/login", ['account' => 'abin', 'password' => 'secret123'])
            ->assertRedirect("/t/{$t->slug}");
        $this->assertAuthenticated();
    }

    public function test_mock_wechat_login_creates_user_and_authenticates(): void
    {
        $t = $this->makeTenant();

        $this->get("/t/{$t->slug}/login/wechat")
            ->assertRedirect("/t/{$t->slug}");
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['tenant_id' => $t->id, 'role' => 'villager']);
    }

    public function test_logout_returns_guest(): void
    {
        $t = $this->makeTenant();
        $this->get("/t/{$t->slug}/login/wechat");

        $this->post("/t/{$t->slug}/logout")
            ->assertRedirect("/t/{$t->slug}");
        $this->assertGuest();
    }

    public function test_bind_phone_for_wechat_user(): void
    {
        $t = $this->makeTenant();
        $this->get("/t/{$t->slug}/login/wechat");

        $this->post("/t/{$t->slug}/login/bind-phone", ['phone' => '13900000000'])
            ->assertSessionHas('status');
        $this->assertDatabaseHas('users', ['phone' => '13900000000']);
    }

    public function test_bind_phone_blocks_existing_phone(): void
    {
        $t = $this->makeTenant();
        User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000000',
            'password' => 'secret123',
            'role' => UserRole::Villager->value,
        ]);
        $this->get("/t/{$t->slug}/login/wechat");

        $this->post("/t/{$t->slug}/login/bind-phone", ['phone' => '13900000000'])
            ->assertStatus(422);
    }
}
