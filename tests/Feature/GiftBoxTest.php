<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\GiftBox;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 3.3 节日礼盒：权益额度创建+定制（亲笔签落盘）、后台制作/发货/送达、
 * 公开扫码落地页、租户隔离、权限。
 */
class GiftBoxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private int $phoneCounter = 0;

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function makeActiveAdopter(): User
    {
        $this->phoneCounter++;
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '1381'.str_pad((string) $this->phoneCounter, 7, '0', STR_PAD_LEFT),
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);

        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001', 'province' => '宁夏',
            'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ]);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '云乡民的田');

        return $user;
    }

    public function test_quota_create_and_customize_with_signature(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();
        Storage::fake('public');

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$adoption->id}/gifts", ['festival' => 'spring'])
            ->assertRedirect();

        $giftBox = GiftBox::first();
        $this->assertNotNull($giftBox);
        $this->assertSame('spring', $giftBox->festival->value);
        $this->assertNotNull($giftBox->code);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$adoption->id}/gifts/{$giftBox->id}/customize", [
                'recipient_name' => '李四',
                'recipient_phone' => '13900000000',
                'message' => '中秋快乐',
                'signature' => 'data:image/png;base64,'.base64_encode('fake-png'),
            ])
            ->assertRedirect();

        $giftBox->refresh();
        $this->assertSame('李四', $giftBox->recipient_name);
        $this->assertSame('中秋快乐', $giftBox->message);
        $this->assertNotNull($giftBox->signature_image);
        Storage::disk('public')->assertExists($giftBox->signature_image);
    }

    public function test_quota_exhausted_is_422(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$adoption->id}/gifts", ['festival' => 'spring'])
            ->assertRedirect();
        // 额度 1 已用完 → 第二次 422
        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$adoption->id}/gifts", ['festival' => 'spring'])
            ->assertStatus(422);
    }

    public function test_admin_making_ship_delivered_flow(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();
        $giftBox = GiftBox::create([
            'tenant_id' => $t->id,
            'adoption_id' => $adoption->id,
            'festival' => 'mid_autumn',
            'year' => (int) now()->format('Y'),
            'code' => 'GB-TEST-001',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/gift-boxes/{$giftBox->id}/making")
            ->assertRedirect();
        $this->assertSame('making', $giftBox->fresh()->status->value);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/gift-boxes/{$giftBox->id}/ship", ['tracking_no' => 'SF-GIFT-1', 'carrier' => '顺丰'])
            ->assertRedirect();
        $this->assertSame('shipped', $giftBox->fresh()->status->value);
        $this->assertSame('SF-GIFT-1', $giftBox->fresh()->tracking_no);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/gift-boxes/{$giftBox->id}/delivered")
            ->assertRedirect();
        $this->assertSame('delivered', $giftBox->fresh()->status->value);
    }

    public function test_public_scan_landing_page(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();
        $giftBox = GiftBox::create([
            'tenant_id' => $t->id,
            'adoption_id' => $adoption->id,
            'festival' => 'spring',
            'year' => (int) now()->format('Y'),
            'code' => 'GB-SCAN-001',
            'recipient_name' => '收礼人甲',
            'message' => '新年快乐',
            'signature_image' => 'gift-signatures/GB-SCAN-001.png',
            'status' => 'shipped',
        ]);

        $this->get("/t/{$t->slug}/gift/{$giftBox->code}")
            ->assertOk()
            ->assertSee('祝福')
            ->assertSee('新年快乐')
            ->assertSee('成为云乡民');
    }

    public function test_unknown_and_cross_tenant_code_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("/t/{$t->slug}/gift/NOTEXIST")->assertNotFound();

        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();
        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);
        $otherBox = GiftBox::create([
            'tenant_id' => $other->id,
            'adoption_id' => $adoption->id,
            'festival' => 'spring',
            'year' => now()->year,
            'code' => 'GB-OTHER-001',
            'status' => 'draft',
        ]);

        $this->get("/t/{$t->slug}/gift/{$otherBox->code}")->assertNotFound();
    }

    public function test_villager_cannot_access_admin_gift_boxes(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/admin/gift-boxes")
            ->assertForbidden();
    }

    public function test_non_owner_cannot_view_gifts(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $owner = $this->makeActiveAdopter();
        $other = $this->makeActiveAdopter();
        $adoption = $owner->adoptions()->first();

        $this->actingAs($other)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}/gifts")
            ->assertNotFound();
    }
}
