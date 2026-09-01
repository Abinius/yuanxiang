<?php

namespace Tests\Feature;

use App\Enums\FarmLogType;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\FarmMember;
use App\Models\FertilizerBatch;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 2.4 家人移动录入端：按 farm_members.permission_scope 限权，
 * 录农事动态/直播预告（farm_logs）、有机肥批次（fertilizer_batches）、采收（harvests）。
 * tenant_admin 直通；无 membership 或 scope 缺失 → 403。
 */
class FamilyEntryTest extends TestCase
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

    private function plot(): Plot
    {
        return Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->first();
    }

    private function villager(string $phone = '13800000001'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
        ]);
    }

    /** 建 family 用户 + farm_member（relation=father，给定 scope）。 */
    private function familyUser(array $scopes, string $phone = '13900000001'): User
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '阿叔',
            'role' => 'family',
        ]);

        FarmMember::create([
            'tenant_id' => $t->id,
            'user_id' => $user->id,
            'farm_id' => $this->farm()->id,
            'relation' => 'father',
            'permission_scope' => $scopes,
        ]);

        return $user;
    }

    // ── 发动态 / 直播预告 ─────────────────────────────────────

    public function test_family_with_farm_log_scope_creates_log_with_image(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser(['farm_log']);
        Storage::fake('public');
        $image = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($user)
            ->get("/t/{$t->slug}/family/logs/create")
            ->assertOk()
            ->assertSee('直播'); // 直播预告并入类型下拉

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $this->plot()->id,
                'type' => 'fertilize',
                'title' => '今日施肥',
                'content' => '施生物菌肥',
                'occurred_at' => '2026-08-30',
                'is_public' => '1',
                'images' => [$image],
            ])
            ->assertRedirect();

        $log = FarmLog::where('title', '今日施肥')->firstOrFail();
        $this->assertEquals($t->id, $log->tenant_id);
        $this->assertEquals($this->plot()->id, $log->plot_id);
        $this->assertEquals($user->id, $log->author_id);
        $this->assertEquals(FarmLogType::Fertilize, $log->type);
        $this->assertTrue($log->is_public);
        // 2.5：施肥/采收/检测自动进溯源时间线
        $this->assertTrue($log->is_trace_node);
        $this->assertNull($log->fertilizer_batch_id); // 本次未带批次
        $this->assertEquals('family', $log->source);
        $this->assertCount(1, $log->images);
        $this->assertStringContainsString('farm-logs/', $log->images[0]);
    }

    public function test_family_without_farm_log_scope_is_forbidden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser(['fertilizer']); // 无 farm_log scope

        $this->actingAs($user)
            ->get("/t/{$t->slug}/family/logs/create")
            ->assertForbidden();
    }

    public function test_fertilize_log_with_batch_sets_trace_node_and_attaches_batch(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser(['farm_log']);
        $batch = FertilizerBatch::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'batch_no' => 'NXLB-2026-001',
            'produced_at' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $this->plot()->id,
                'type' => 'fertilize',
                'title' => '基施',
                'content' => 'NXLB 基施',
                'occurred_at' => '2026-03-12',
                'is_public' => '1',
                'fertilizer_batch_id' => (string) $batch->id,
            ])
            ->assertRedirect();

        $log = FarmLog::where('title', '基施')->firstOrFail();
        $this->assertTrue($log->is_trace_node);
        $this->assertEquals($batch->id, $log->fertilizer_batch_id);
    }

    public function test_daily_log_stays_non_trace_node(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser(['farm_log']);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $this->plot()->id,
                'type' => 'daily',
                'title' => '巡田',
                'is_public' => '1',
            ])
            ->assertRedirect();

        $log = FarmLog::where('title', '巡田')->firstOrFail();
        $this->assertFalse($log->is_trace_node);
        $this->assertNull($log->fertilizer_batch_id);
    }

    // ── 有机肥批次 ────────────────────────────────────────────

    public function test_family_with_fertilizer_scope_creates_batch(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser(['fertilizer']);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/fertilizer", [
                'batch_no' => 'NXLB-2026-001',
                'produced_at' => '2026-08-01',
                'nxlb_ref' => 'NXLB-REF-01',
                'ingredients' => '有机质≥45%，pH 6.8',
            ])
            ->assertRedirect();

        $batch = FertilizerBatch::where('batch_no', 'NXLB-2026-001')->firstOrFail();
        $this->assertEquals($t->id, $batch->tenant_id);
        $this->assertEquals($this->farm()->id, $batch->farm_id);
        $this->assertStringContainsString('有机质', $batch->ingredients);
    }

    // ── 采收 ──────────────────────────────────────────────────

    public function test_family_with_harvest_scope_creates_harvest(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser(['harvest']);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/harvest", [
                'plot_id' => $this->plot()->id,
                'season_year' => 2026,
                'harvested_at' => '2026-09-10',
                'dry_weight_kg' => '12.5',
                'quality_grade' => '一级',
            ])
            ->assertRedirect();

        $harvest = Harvest::where('tenant_id', $t->id)->firstOrFail();
        $this->assertEquals($user->id, $harvest->handler_id);
        $this->assertEquals($this->plot()->id, $harvest->plot_id);
        $this->assertEquals(2026, $harvest->season_year);
        $this->assertEquals(12.5, (float) $harvest->dry_weight_kg);
    }

    // ── 限权边界 ──────────────────────────────────────────────

    public function test_family_without_membership_dashboard_grey_and_post_forbidden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000099',
            'password' => 'secret123',
            'nickname' => '无权限家人',
            'role' => 'family',
        ]);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/family")
            ->assertOk()
            ->assertSee('暂无');

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $this->plot()->id,
                'type' => 'daily',
                'title' => '越权',
                'is_public' => '1',
            ])
            ->assertForbidden();
    }

    public function test_villager_wrong_role_is_forbidden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->villager())
            ->get("/t/{$t->slug}/family")
            ->assertForbidden();
    }

    public function test_tenant_admin_bypasses_scope_without_membership(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $this->plot()->id,
                'type' => 'daily',
                'title' => '管理员代录',
                'is_public' => '1',
            ])
            ->assertRedirect();

        $log = FarmLog::where('title', '管理员代录')->firstOrFail();
        $this->assertEquals($admin->id, $log->author_id);
        $this->assertEquals('family', $log->source);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->seed([BaseSeeder::class]);
        $t = $this->tenant();

        $this->get("/t/{$t->slug}/family")
            ->assertRedirect("/t/{$t->slug}/login");
    }

    public function test_family_dashboard_shows_chinese_scope_and_recent_entries(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser(['farm_log', 'fertilizer', 'harvest']);

        FarmLog::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $this->plot()->id,
            'type' => FarmLogType::Daily->value,
            'title' => '巡田记录',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/family")
            ->assertOk()
            ->assertSee('农事动态 / 直播预告') // 中文权限标签
            ->assertSee('最近录入')
            ->assertSee('巡田记录');
    }
}
