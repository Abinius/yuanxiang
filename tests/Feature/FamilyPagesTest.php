<?php

namespace Tests\Feature;

use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Harvest;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 家人录入端页面回归：渲染不 500 + 编辑页必须回填并提交到 update 路由。
 *
 * 背景：这些页面此前只有 store(新建) 用例，编辑页从未被打开过，
 * 结果 compact() 传空参、formData() 实参错位、编辑表单提交到 create 路由
 * 三个 bug 都活到了上线。
 */
class FamilyPagesTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_SCOPES = ['farm_log', 'fertilizer', 'harvest', 'plot'];

    private function tenant()
    {
        return \App\Models\Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function farm()
    {
        return \App\Models\Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    /** 全 scope 的 family 用户（能进家人端每个页面）。 */
    private function familyUser(): \App\Models\User
    {
        $t = $this->tenant();
        $user = \App\Models\User::create([
            'tenant_id' => $t->id, 'phone' => '13900000001', 'password' => 'secret123',
            'nickname' => '阿叔', 'role' => 'family',
        ]);
        \App\Models\FarmMember::create([
            'tenant_id' => $t->id, 'user_id' => $user->id,
            'farm_id' => $this->farm()->id,
            'relation' => 'father', 'permission_scope' => self::ALL_SCOPES,
        ]);

        return $user;
    }

    public function test_all_family_get_pages_render(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->familyUser();

        $plot = \App\Models\Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->first();
        $log = FarmLog::create([
            'tenant_id' => $this->tenant()->id, 'farm_id' => $this->farm()->id,
            'plot_id' => $plot->id, 'author_id' => $user->id, 'type' => 'daily',
            'title' => '巡田', 'content' => '', 'images' => [],
            'occurred_at' => '2026-10-01', 'is_public' => true,
            'source' => 'family', 'is_trace_node' => false,
        ]);
        $batch = FertilizerBatch::create([
            'tenant_id' => $this->tenant()->id,
            'farm_id' => $this->farm()->id,
            'batch_no' => 'NXLB-PREV-01', 'produced_at' => '2026-09-10',
        ]);
        $harvest = Harvest::create([
            'tenant_id' => $this->tenant()->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $plot->id, 'handler_id' => $user->id,
            'season_year' => 2026, 'harvested_at' => '2026-09-20', 'dry_weight_kg' => '3.5',
        ]);

        $paths = [
            '/family',
            '/family/logs/create',
            '/family/logs/create?type=live_broadcast',
            '/family/logs/'.$log->id.'/edit',
            '/family/fertilizer/create',
            '/family/fertilizer/'.$batch->id.'/edit',
            '/family/harvest/create',
            '/family/harvest/'.$harvest->id.'/edit',
            '/family/plots',
            '/family/plots/create',
            '/family/plots/'.$plot->id.'/edit',
        ];

        foreach ($paths as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    public function test_edit_log_prefills_and_submits_to_update_route(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->familyUser();
        $t = $this->tenant();

        // 表单只能有一个 is_public 输入框：再放一个 hidden="0" 会和勾选框一起提交成数组，
        // boolean 校验直接拒收，表单在默认「公开」状态下根本保存不了。
        $this->actingAs($user)->get('/family/logs/create')->assertOk()
            ->assertDontSee('name="is_public" value="0"', false);

        $this->actingAs($user)->post('/family/logs', [
            'plot_id' => \App\Models\Plot::where('tenant_id', $t->id)->where('type', 'plot')->first()->id,
            'type' => 'daily', 'title' => '巡田记录', 'is_public' => '1',
        ])->assertRedirect();

        $log = FarmLog::where('title', '巡田记录')->firstOrFail();

        $this->assertTrue($log->is_public, '勾选 is_public 后必须存成公开');

        // 编辑页回填原值 + 提交目标是 update 路由（不是 create，否则会建重复记录）
        $this->actingAs($user)->get('/family/logs/'.$log->id.'/edit')
            ->assertOk()
            ->assertSee('巡田记录', false)
            ->assertSee('action="'.route('tenant.family.logs.update', ['farm_log' => $log]).'"', false)
            ->assertDontSee('action="'.route('tenant.family.logs.store', []).'"', false);

        $this->actingAs($user)->post('/family/logs/'.$log->id, [
            'plot_id' => $log->plot_id, 'type' => 'daily', 'title' => '改标题', 'is_public' => '1',
        ])->assertRedirect();

        $this->assertSame(1, FarmLog::count());
        $log->refresh();
        $this->assertSame('改标题', $log->title);
    }

    public function test_edit_batch_prefills_and_submits_to_update_route(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->familyUser();

        $this->actingAs($user)->post('/family/fertilizer', [
            'batch_no' => 'NXLB-2026-001', 'produced_at' => '2026-08-01', 'nxlb_ref' => 'NXLB-REF-01',
        ])->assertRedirect();

        $batch = FertilizerBatch::where('batch_no', 'NXLB-2026-001')->firstOrFail();

        $this->actingAs($user)->get('/family/fertilizer/'.$batch->id.'/edit')
            ->assertOk()
            ->assertSee('NXLB-2026-001', false)
            ->assertSee('NXLB-REF-01', false)
            ->assertSee('action="'.route('tenant.family.fertilizer.update', ['batch' => $batch]).'"', false);

        $this->actingAs($user)->post('/family/fertilizer/'.$batch->id, [
            'batch_no' => 'NXLB-2026-002', 'produced_at' => '2026-08-05',
        ])->assertRedirect();

        $this->assertSame(1, FertilizerBatch::count());
        $batch->refresh();
        $this->assertSame('NXLB-2026-002', $batch->batch_no);
    }

    public function test_edit_harvest_prefills_and_submits_to_update_route(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->familyUser();
        $t = $this->tenant();

        $this->actingAs($user)->post('/family/harvest', [
            'plot_id' => \App\Models\Plot::where('tenant_id', $t->id)->where('type', 'plot')->first()->id,
            'season_year' => 2026, 'harvested_at' => '2026-09-10',
            'dry_weight_kg' => '12.5', 'quality_grade' => '一级',
        ])->assertRedirect();

        $harvest = Harvest::where('season_year', 2026)->firstOrFail();

        $this->actingAs($user)->get('/family/harvest/'.$harvest->id.'/edit')
            ->assertOk()
            ->assertSee('一级', false)
            ->assertSee('action="'.route('tenant.family.harvest.update', ['harvest' => $harvest]).'"', false);

        $this->actingAs($user)->post('/family/harvest/'.$harvest->id, [
            'plot_id' => $harvest->plot_id, 'season_year' => 2026,
            'harvested_at' => '2026-09-12', 'dry_weight_kg' => '13.2',
        ])->assertRedirect();

        $this->assertSame(1, Harvest::count());
        $harvest->refresh();
        $this->assertEquals('13.2', (string) $harvest->dry_weight_kg);
        // 品质等级／备注都是可选项，不填必须能存（控制器不能读不存在的键）
        $this->assertNull($harvest->quality_grade);
        $this->assertNull($harvest->notes);
    }
}
