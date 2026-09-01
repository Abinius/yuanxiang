<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 社交媒体分享：公开铭牌落地页外链可打开（非 owner/guest）+ 分享组件复制按钮。
 */
class ShareTest extends TestCase
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

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function makeActiveAdopter(): User
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id, 'phone' => '13800000001', 'password' => 'secret123',
            'nickname' => '云乡民', 'role' => 'villager',
        ]);
        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001', 'province' => '宁夏',
            'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ]);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '阿林的光彩田');

        return $user;
    }

    public function test_public_nameplate_page_opens_for_guest(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();

        // guest 未登录可直接打开公开铭牌落地页（外链分享不 404）
        $this->get("/t/{$t->slug}/nameplate/{$adoption->id}")
            ->assertOk()
            ->assertSee('阿林的光彩田')
            ->assertSee('复制链接')
            ->assertSee('认养这块田');
    }

    public function test_scan_page_renders_share_component(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $plot = $user->adoptions()->first()->adoptable;

        // 溯源页（公开）渲染分享组件
        $this->get("/t/{$t->slug}/trace/{$plot->id}")
            ->assertOk()
            ->assertSee('复制链接');
    }
}
