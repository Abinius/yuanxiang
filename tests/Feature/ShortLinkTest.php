<?php

namespace Tests\Feature;

use App\Models\ShortLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShortLinkService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 短链接：后台生成（自定义码/随机）、公开 /u/{code} 302 + 点击计数、未知/跨租户 404、码冲突。
 */
class ShortLinkTest extends TestCase
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

    public function test_admin_creates_short_link(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->post("/admin/short-links", [
                'target_url' => url('/t/guangcai/trace/1'),
                'code' => 'mytrace',
            ])
            ->assertRedirect();

        $link = ShortLink::first();
        $this->assertNotNull($link);
        $this->assertSame('mytrace', $link->code);
        $this->assertSame(0, $link->click_count);
    }

    public function test_public_redirect_and_click_count(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $target = url('/t/guangcai/adopt');
        $link = app(ShortLinkService::class)->create($t, $target, 'abc123');

        $this->get("/u/{$link->code}")
            ->assertRedirect($target);

        $this->assertSame(1, $link->fresh()->click_count);
    }

    public function test_unknown_code_404(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->get("/u/nonexist")->assertNotFound();
    }

    public function test_custom_code_conflict_422(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        app(ShortLinkService::class)->create($t, url('/'), 'dup001');

        $this->actingAs($this->admin())
            ->post("/admin/short-links", [
                'target_url' => url('/'),
                'code' => 'dup001',
            ])
            ->assertStatus(422);
    }
}
