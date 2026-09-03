<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Farm;
use App\Models\GiftBox;
use App\Models\Plot;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\GiftBoxService;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F8 礼盒拉新闭环：扫码落地页带赠礼人推荐码，下单即归因。
 */
class GiftBoxReferralTest extends TestCase
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

    private function villager(string $phone = '13800000030'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);
    }

    private function makeActiveAdoption(User $user): Adoption
    {
        $plot = Plot::where('type', 'plot')->first();
        $this->actingAs($user)
            ->post("/t/{$this->tenant()->slug}/adopt/{$plot->id}/order", [
                'name' => '张三',
                'phone' => '13800000030',
                'province' => '宁夏',
                'city' => '吴忠',
                'district' => '红寺堡',
                'detail' => '光彩村 1 号',
            ])
            ->assertRedirect();
        $adoption = Adoption::where('adoptable_id', $plot->id)->latest()->firstOrFail();
        app(AdoptionService::class)->confirmMockPayment($adoption);
        app(AdoptionService::class)->signAgreement($adoption, '阿林的光彩田');
        return $adoption->fresh();
    }

    private function makeGiftBox(Adoption $adoption): GiftBox
    {
        return (new GiftBoxService())->create($adoption, 'spring', (int) now()->format('Y'));
    }

    /** 扫码落地页 CTA 链接携带赠礼人推荐码 ?ref=。 */
    public function test_scan_page_cta_carries_referral_code(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        // 建一个 referral 类型促销，让 getOrCreateReferral 能生成推荐码
        $promo = Promotion::create([
            'tenant_id' => $t->id,
            'type' => 'referral',
            'status' => 'active',
            'name' => '推荐有礼',
        ]);

        $giftBox = $this->makeGiftBox($adoption);

        $this->get("/t/{$t->slug}/gift/{$giftBox->code}")
            ->assertOk()
            ->assertSee('ref=REF');
    }

    /** 扫码落地页正常渲染赠礼人信息与成为云乡民 CTA。 */
    public function test_scan_page_shows_giver_and_cta(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);
        $giftBox = $this->makeGiftBox($adoption);

        $this->get("/t/{$t->slug}/gift/{$giftBox->code}")
            ->assertOk()
            ->assertSee('云乡民')
            ->assertSee('成为云乡民');
    }

    /** 跨租户礼盒码 404（TenantScoped 隔离）。 */
    public function test_scan_page_isolates_other_tenants(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);
        $giftBox = $this->makeGiftBox($adoption);

        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);

        $this->get("/t/{$other->slug}/gift/{$giftBox->code}")
            ->assertNotFound();
    }
}