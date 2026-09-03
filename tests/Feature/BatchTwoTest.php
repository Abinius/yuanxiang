<?php

namespace Tests\Feature;

use App\Enums\FarmLogType;
use App\Models\Adoption;
use App\Models\FarmMember;
use App\Models\FarmLog;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 第二批：G1 快速记录卡 / G4 今日待办 / G2 常用地块 / F5 转化前回放 / G6 露脸解说。
 */
class BatchTwoTest extends TestCase
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

    private function familyUser(string $scope = 'farm_log', ?string $phone = null): User
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => $phone ?? '13900000060',
            'password' => 'secret123',
            'nickname' => '阿叔',
            'role' => 'family',
        ]);
        FarmMember::create([
            'tenant_id' => $t->id,
            'user_id' => $user->id,
            'farm_id' => $t->farms()->firstOrFail()->id,
            'relation' => 'father',
            'permission_scope' => [$scope],
        ]);
        return $user;
    }

    private function villager(string $phone = '13800000060'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
            'openid' => 'mock_batch2',
        ]);
    }

    // ── G1 快速记录卡 + G6 露脸解说入口 ─────────────────────

    /** 家人端显示快速记录卡（发动态 / 录解说 / 直播预告）。 */
    public function test_family_dashboard_shows_quick_record_cards(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser('farm_log');

        $this->actingAs($user)
            ->get("/t/{$t->slug}/family")
            ->assertOk()
            ->assertSee('快速记录')
            ->assertSee('录解说（露脸）')
            ->assertSee('直播预告')
            ->assertSee('今日待办');
    }

    // ── G4 今日待办 ────────────────────────────────────────

    /** 家人端今日待办显示当月物候 + 待制作礼盒 + 临期认养。 */
    public function test_family_dashboard_shows_today_todos(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser('farm_log');

        $stage = config('goji.stages')[(int) now()->format('n')]['label'];

        $this->actingAs($user)
            ->get("/t/{$t->slug}/family")
            ->assertOk()
            ->assertSee('今日待办')
            ->assertSee($stage)
            ->assertSee('待制作礼盒')
            ->assertSee('30 天内到期认养');
    }

    // ── G2 常用地块置顶 ────────────────────────────────────

    /** 发动态时最近用过的地块排在最前。 */
    public function test_farm_log_create_orders_recent_plot_first(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser('farm_log');

        $plots = Plot::where('tenant_id', $t->id)->where('type', 'plot')->orderBy('code')->get();
        $first = $plots[0];
        $second = $plots[1];

        // 上次用了 second
        FarmLog::create([
            'tenant_id' => $t->id,
            'farm_id' => $t->farms()->firstOrFail()->id,
            'plot_id' => $second->id,
            'author_id' => $user->id,
            'type' => 'daily',
            'title' => '上次用的田',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        $resp = $this->actingAs($user)
            ->get("/t/{$t->slug}/family/logs/create")
            ->assertOk();

        $content = $resp->getContent();
        $secondPos = strpos($content, 'value="'.$second->id.'"');
        $firstPos = strpos($content, 'value="'.$first->id.'"');
        $this->assertNotFalse($secondPos, '最近用过的地块应在列表');
        $this->assertTrue($secondPos < $firstPos, '最近用过的地块应排最前');
    }

    // ── G6 露脸解说 ────────────────────────────────────────

    /** 家人可录露脸解说（type=explain + 视频 + payload.stage/duration），推送云乡民。 */
    public function test_family_records_explain_log_with_video(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser('farm_log');
        Storage::fake('public');
        $video = UploadedFile::fake()->create('explain.mp4', 100, 'video/mp4');

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => Plot::where('tenant_id', $t->id)->where('type', 'plot')->first()->id,
                'type' => 'explain',
                'content' => '这是阿叔的解说',
                'occurred_at' => now()->toDateString(),
                'is_public' => '1',
                'video_url' => $video,
                'video_duration' => '45',
            ])
            ->assertRedirect();

        $log = FarmLog::where('type', 'explain')->firstOrFail();
        $this->assertSame('explain', $log->type->value);
        $this->assertTrue($log->is_public);
        $this->assertStringContainsString('farm-logs/', $log->video_url);
        $this->assertSame(45, (int) ($log->payload['duration'] ?? 0));
        $this->assertNotEmpty($log->payload['stage'] ?? '');
    }

    /** explain 类型不生成默认标题但保留用户内容。 */
    public function test_explain_log_is_public_by_default(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyUser('farm_log');

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => Plot::where('tenant_id', $t->id)->where('type', 'plot')->first()->id,
                'type' => 'explain',
                'title' => '',
                'is_public' => '1',
            ])
            ->assertRedirect();

        $log = FarmLog::where('type', 'explain')->firstOrFail();
        $this->assertTrue($log->is_public);
        $this->assertNotEmpty($log->title); // 自动生成
        $this->assertStringContainsString('解说', $log->title);
    }

    // ── F5 转化前回放 ──────────────────────────────────────

    /** 认养页（公开，无 auth）显示家人露脸解说/直播回放。 */
    public function test_adopt_index_shows_public_replays_without_auth(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->first();

        FarmLog::create([
            'tenant_id' => $t->id,
            'farm_id' => $t->farms()->firstOrFail()->id,
            'plot_id' => $plot->id,
            'type' => 'explain',
            'title' => '阿叔的秋果解说',
            'content' => '看果实成熟',
            'video_url' => 'farm-logs/demo.mp4',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        // 未登录访问公开认养页仍可见回放（去 auth 墙）
        $this->get("/t/{$t->slug}/adopt")
            ->assertOk()
            ->assertSee('精彩回放')
            ->assertSee('阿叔的秋果解说');
    }

    /** 认养页回放只显示公开 + 有视频的内容。 */
    public function test_adopt_index_replays_exclude_private_and_no_video(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->first();

        FarmLog::create([
            'tenant_id' => $t->id, 'farm_id' => $t->farms()->firstOrFail()->id,
            'plot_id' => $plot->id, 'type' => 'explain', 'title' => '私密解说',
            'video_url' => 'farm-logs/private.mp4', 'occurred_at' => now(),
            'is_public' => false, 'source' => 'family',
        ]);
        FarmLog::create([
            'tenant_id' => $t->id, 'farm_id' => $t->farms()->firstOrFail()->id,
            'plot_id' => $plot->id, 'type' => 'daily', 'title' => '日常无视频',
            'occurred_at' => now(), 'is_public' => true, 'source' => 'family',
        ]);

        $this->get("/t/{$t->slug}/adopt")
            ->assertOk()
            ->assertDontSee('私密解说')
            ->assertDontSee('日常无视频');
    }
}