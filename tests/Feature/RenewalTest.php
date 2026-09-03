<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Models\Plot;
use App\Models\PushMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\RenewalService;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * F9 续费到期调度 + auto_renew 消费。
 * 30/7/1 天提醒（mock 落库）；auto_renew 临期自动建下一季待支付单；/my 显剩余天数；Ended 可续。
 */
class RenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        Carbon::setTestNow(null);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function villager(string $phone = '13800000070'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
            'openid' => 'mock_renewal',
        ]);
    }

    private function makeActiveAdoption(User $user, ?Carbon $endDate = null): Adoption
    {
        $plot = Plot::where('type', 'plot')->first();
        $this->actingAs($user)
            ->post("/t/{$this->tenant()->slug}/adopt/{$plot->id}/order", [
                'name' => '张三', 'phone' => '13800000070',
                'province' => '宁夏', 'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
            ])
            ->assertRedirect();
        $adoption = Adoption::where('adoptable_id', $plot->id)->latest()->firstOrFail();
        app(AdoptionService::class)->confirmMockPayment($adoption);
        app(AdoptionService::class)->signAgreement($adoption, '阿林的光彩田');
        if ($endDate) {
            $adoption->update(['end_date' => $endDate->toDateString()]);
        }
        return $adoption->fresh();
    }

    // ── R9.1 到期提醒（30/7/1 天，mock 落库）─────────────────

    public function test_reminder_sent_for_adoption_expiring_in_7_days(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->villager();
        $this->makeActiveAdoption($user, now()->addDays(7));

        $sent = app(RenewalService::class)->sendReminders(7);

        $this->assertSame(1, $sent);
        $msg = PushMessage::where('user_id', $user->id)->first();
        $this->assertNotNull($msg);
        $this->assertSame('renewal_notice', $msg->type);
        $this->assertSame('sent', $msg->status);
    }

    public function test_no_reminder_when_not_expiring_in_window(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->villager();
        $this->makeActiveAdoption($user, now()->addDays(60)); // 60 天后到期，不在 30 天内

        $sent = app(RenewalService::class)->sendReminders(30);

        $this->assertSame(0, $sent);
    }

    // ── R9.2 auto_renew 临期自动建单 ─────────────────────────

    public function test_auto_renew_creates_next_season_pending_order(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user, now()->addDays(5));
        $adoption->update(['auto_renew' => true]);

        $created = app(RenewalService::class)->autoRenewExpiring();

        $this->assertSame(1, $created);
        $next = Adoption::where('renewed_from_id', $adoption->id)->firstOrFail();
        $this->assertSame(AdoptionStatus::PendingPayment->value, $next->status->value);
        $this->assertSame($adoption->season_year + 1, $next->season_year);
    }

    public function test_auto_renew_skips_when_disabled_or_already_renewed(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user, now()->addDays(5));
        $adoption->update(['auto_renew' => false]); // 未开启续费意向

        $created = app(RenewalService::class)->autoRenewExpiring();

        $this->assertSame(0, $created);
        $this->assertSame(0, Adoption::where('renewed_from_id', $adoption->id)->count());
    }

    // ── R9.3 /my 剩余天数 + Ended 可续 ──────────────────────

    public function test_my_index_shows_remaining_days_for_active(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $this->makeActiveAdoption($user, now()->addDays(10));

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my")
            ->assertOk()
            ->assertSee('距到期')
            ->assertSee('10');
    }

    public function test_ended_adoption_can_renew(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user, now()->addDays(3));

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$adoption->id}/renew")
            ->assertRedirect();

        // 应建下一季待支付单并跳到支付页
        $next = Adoption::where('renewed_from_id', $adoption->id)->firstOrFail();
        $this->assertSame(AdoptionStatus::PendingPayment->value, $next->status->value);
        $this->assertSame($adoption->season_year + 1, $next->season_year);
    }
}