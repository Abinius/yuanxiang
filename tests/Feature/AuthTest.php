<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function makeTenant(string $slug = 'guangcai'): Tenant
    {
        return Tenant::create(['slug' => $slug, 'name' => '光彩云村庄', 'status' => 'active']);
    }

    public function test_login_page_renders_both_tabs(): void
    {
        $t = $this->makeTenant();
        $this->get("/login")
            ->assertOk()
            ->assertSee('账号登录')
            ->assertSee('微信一键登录')
            ->assertSee('服务协议')
            ->assertSee('隐私政策');
    }

    public function test_password_login_succeeds_and_redirects(): void
    {
        $t = $this->makeTenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'nickname' => '阿林',
            'role' => UserRole::Villager->value,
        ]);

        $this->post("/login", ['account' => '13800000001', 'password' => 'secret123', 'agreed' => '1'])
            ->assertRedirect("/");
        $this->assertAuthenticated();
        $this->assertNotNull($user->fresh()->agreement_accepted_at);
    }

    public function test_password_login_without_acceptance_is_rejected(): void
    {
        $t = $this->makeTenant();
        User::create([
            'tenant_id' => $t->id,
            'phone' => '13800000002',
            'password' => 'secret123',
            'role' => UserRole::Villager->value,
        ]);

        $this->post("/login", ['account' => '13800000002', 'password' => 'secret123'])
            ->assertSessionHasErrors('agreed');
        $this->assertGuest();
    }

    public function test_wechat_login_stamps_agreement_acceptance(): void
    {
        $this->makeTenant();

        $this->get("/login/wechat")
            ->assertRedirect("/");
        $this->assertAuthenticated();
        $this->assertNotNull(auth()->user()->fresh()->agreement_accepted_at);
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

        $this->post("/login", ['account' => '13800000001', 'password' => 'wrong', 'agreed' => '1'])
            ->assertSessionHasErrors('account');
        $this->assertGuest();
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

        $this->post("/login", ['account' => 'abin', 'password' => 'secret123', 'agreed' => '1'])
            ->assertRedirect("/");
        $this->assertAuthenticated();
    }

    public function test_mock_wechat_login_creates_user_and_authenticates(): void
    {
        $t = $this->makeTenant();

        $this->get("/login/wechat")
            ->assertRedirect("/");
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['tenant_id' => $t->id, 'role' => 'villager']);
    }

    public function test_logout_returns_guest(): void
    {
        $t = $this->makeTenant();
        $this->get("/login/wechat");

        $this->post("/logout")
            ->assertRedirect("/");
        $this->assertGuest();
    }

    public function test_bind_phone_for_wechat_user(): void
    {
        $t = $this->makeTenant();
        $this->get("/login/wechat");

        $this->post("/login/bind-phone", ['phone' => '13900000000'])
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
        $this->get("/login/wechat");

        $this->post("/login/bind-phone", ['phone' => '13900000000'])
            ->assertStatus(422);
    }
}
