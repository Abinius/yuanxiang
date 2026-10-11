<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Adoption;
use App\Models\Farm;
use App\Models\FarmMember;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F1 田地动态化：admin CRUD + 删除保护 + 家人端录入。
 */
class PlotManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function admin(Tenant $t): User
    {
        return User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000001',
            'username' => '13900000001',
            'password' => 'secret123',
            'nickname' => '管理员',
            'role' => UserRole::TenantAdmin->value,
        ]);
    }

    private function familyMember(Tenant $t, Farm $farm): array
    {
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000002',
            'username' => '13900000002',
            'password' => 'secret123',
            'nickname' => '家人',
            'role' => UserRole::Family->value,
        ]);
        $member = FarmMember::create([
            'tenant_id' => $t->id,
            'user_id' => $user->id,
            'farm_id' => $farm->id,
            'relation' => '家人',
            'permission_scope' => ['plot', 'farm_log'],
        ]);

        return [$user, $member];
    }

    private function cover(): UploadedFile
    {
        return UploadedFile::fake()->image('cover.jpg', 600, 800);
    }

    /** 给地块挂一张认养单；$paymentStatus 为 null 表示从未付款。 */
    private function adoptionOn(Plot $plot, User $owner, AdoptionStatus $status, ?PaymentStatus $paymentStatus = null): Adoption
    {
        $t = $this->tenant();

        $adoption = Adoption::create([
            'tenant_id' => $t->id, 'user_id' => $owner->id,
            'adoption_no' => 'TEST-DEL-'.uniqid(),
            'adoptable_type' => Plot::class, 'adoptable_id' => $plot->id,
            'plan_id' => Plan::where('tenant_id', $t->id)->firstOrFail()->id,
            'farm_id' => $plot->farm_id,
            'season_year' => 2026, 'annual_fee' => 5000,
            'start_date' => now(), 'end_date' => now()->addYear(),
            'status' => $status->value,
        ]);

        if ($paymentStatus === null) return $adoption;

        Payment::create([
            'tenant_id' => $t->id, 'payable_type' => Adoption::class, 'payable_id' => $adoption->id,
            'amount' => 5000, 'method' => 'manual', 'status' => $paymentStatus->value,
            'paid_at' => $paymentStatus === PaymentStatus::Pending ? null : now(),
        ]);

        return $adoption;
    }

    private function plotData(Farm $farm, Plan $plan, string $code = 'FD-NEW-01'): array
    {
        return [
            'farm_id' => $farm->id,
            'plan_id' => $plan->id,
            'type' => 'plot',
            'code' => $code,
            'mu_area' => 0.1,
            'price_yearly' => 5000,
            'status' => 'available',
            'order_index' => 0,
            'story' => '测试地块故事',
        ];
    }

    public function test_admin_can_create_plot(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $this->actingAs($admin)
            ->post("/admin/plots", $this->plotData($farm, $plan))
            ->assertRedirect(route('tenant.admin.plots.index', []));

        $this->assertDatabaseHas('plots', [
            'tenant_id' => $t->id,
            'code' => 'FD-NEW-01',
            'story' => '测试地块故事',
        ]);
    }

    public function test_admin_can_update_plot(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-UP-01'), ['tenant_id' => $t->id]));

        $this->actingAs($admin)
            ->put("/admin/plots/{$plot->id}", array_merge($this->plotData($farm, $plan, 'FD-UP-01'), ['story' => '改后的故事']))
            ->assertRedirect(route('tenant.admin.plots.index', []));

        $this->assertSame('改后的故事', $plot->fresh()->story);
    }

    public function test_admin_can_delete_plot_without_adoptions(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-DEL-01'), ['tenant_id' => $t->id]));

        $this->actingAs($admin)
            ->delete("/admin/plots/{$plot->id}")
            ->assertRedirect(route('tenant.admin.plots.index', []));

        $this->assertSoftDeleted('plots', ['id' => $plot->id]);
    }

    public function test_admin_cannot_delete_plot_with_active_adoption(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);
        $villager = User::create([
            'tenant_id' => $t->id, 'phone' => '13800000010', 'username' => '13800000010',
            'password' => 'secret123', 'nickname' => '云乡民', 'role' => UserRole::Villager->value,
        ]);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-LOCK-01'), ['tenant_id' => $t->id]));

        // 在约认养（生效中，未付款）
        $this->adoptionOn($plot, $villager, AdoptionStatus::Active);

        $this->assertTrue($plot->fresh()->hasInFlightAdoptions());

        $this->actingAs($admin)
            ->delete("/admin/plots/{$plot->id}")
            ->assertStatus(409);

        $this->assertNotSoftDeleted('plots', ['id' => $plot->id]);
    }

    public function test_admin_can_delete_ended_unpaid_plot(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-EXP-01'), ['tenant_id' => $t->id]));

        // 已到期但从未付款 → 没收过钱，可删
        $this->adoptionOn($plot, $admin, AdoptionStatus::Ended);

        $this->actingAs($admin)
            ->delete("/admin/plots/{$plot->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('plots', ['id' => $plot->id]);
    }

    /** 本轮核心缺口：已到期 = 已收过钱，不能删。 */
    public function test_admin_cannot_delete_ended_paid_plot(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-EPAID-01'), ['tenant_id' => $t->id]));

        $this->adoptionOn($plot, $admin, AdoptionStatus::Ended, PaymentStatus::Paid);

        $this->actingAs($admin)
            ->delete("/admin/plots/{$plot->id}")
            ->assertStatus(409);

        $this->assertNotSoftDeleted('plots', ['id' => $plot->id]);
    }

    /** 已退款也是付过款：钱退出去了，记录仍在。 */
    public function test_admin_cannot_delete_refunded_plot(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-REF-01'), ['tenant_id' => $t->id]));

        $this->adoptionOn($plot, $admin, AdoptionStatus::Cancelled, PaymentStatus::Refunded);

        $this->actingAs($admin)
            ->delete("/admin/plots/{$plot->id}")
            ->assertStatus(409);

        $this->assertNotSoftDeleted('plots', ['id' => $plot->id]);
    }

    /** 只有未付款弃付单的地块可删，删除时顺带取消弃付单，不留孤儿认养。 */
    public function test_deleting_plot_cancels_its_pending_orders(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-PEND-01'), ['tenant_id' => $t->id]));

        $pending = $this->adoptionOn($plot, $admin, AdoptionStatus::PendingPayment);

        $this->actingAs($admin)
            ->delete("/admin/plots/{$plot->id}")
            ->assertRedirect()
            ->assertSessionHas('ok', '地块「FD-PEND-01」已删除，并取消其 1 张未付款订单。');

        $this->assertSoftDeleted('plots', ['id' => $plot->id]);
        $this->assertSame(AdoptionStatus::Cancelled, $pending->fresh()->status);
    }

    public function test_family_can_create_plot_within_own_farm(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        [$user] = $this->familyMember($t, $farm);

        $data = $this->plotData($farm, $plan, 'FD-FAM-01');
        unset($data['farm_id']); // 家人表单不带 farm_id（控制器锁定）

        $this->actingAs($user)
            ->post("/family/plots", $data)
            ->assertRedirect(route('tenant.family.plots.index', []));

        $this->assertDatabaseHas('plots', [
            'tenant_id' => $t->id, 'code' => 'FD-FAM-01', 'farm_id' => $farm->id,
        ]);
    }

    public function test_family_cannot_edit_plot_from_other_farm(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();

        // 他基地 + 其上地块
        $otherFarm = Farm::create(['tenant_id' => $t->id, 'name' => '他基地']);
        $otherPlot = Plot::create(array_merge($this->plotData($otherFarm, $plan, 'FD-OTH-01'), ['tenant_id' => $t->id]));

        [$user] = $this->familyMember($t, $farm);

        $data = $this->plotData($otherFarm, $plan, 'FD-OTH-01');
        unset($data['farm_id']);

        $this->actingAs($user)
            ->put("/family/plots/{$otherPlot->id}", $data)
            ->assertForbidden();
    }

    public function test_family_can_delete_own_farm_plot(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        [$user] = $this->familyMember($t, $farm);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-FADEL-01'), ['tenant_id' => $t->id]));

        // 列表给出删除入口（此前的文案是「删除请联系租户管理员」）
        $this->actingAs($user)->get('/family/plots')->assertOk()
            ->assertSee('plots/'.$plot->id.'" onsubmit=', false);

        $this->actingAs($user)
            ->delete("/family/plots/{$plot->id}")
            ->assertRedirect()
            ->assertSessionHas('ok', '地块「FD-FADEL-01」已删除。');

        $this->assertSoftDeleted('plots', ['id' => $plot->id]);
    }

    /** 跨基地删除 → 403（与既有编辑口径一致）。 */
    public function test_family_cannot_delete_other_farm_plot(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $otherFarm = Farm::create(['tenant_id' => $t->id, 'name' => '他基地']);
        $otherPlot = Plot::create(array_merge($this->plotData($otherFarm, $plan, 'FD-FODEL-01'), ['tenant_id' => $t->id]));

        [$user] = $this->familyMember($t, $farm);

        $this->actingAs($user)->delete("/family/plots/{$otherPlot->id}")->assertForbidden();

        $this->assertNotSoftDeleted('plots', ['id' => $otherPlot->id]);
    }

    /** 无 plot scope 的家人调不到删除入口。 */
    public function test_family_without_plot_scope_cannot_delete(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-FOPE-01'), ['tenant_id' => $t->id]));

        $user = User::create([
            'tenant_id' => $t->id, 'phone' => '13900000003', 'username' => '13900000003',
            'password' => 'secret123', 'nickname' => '家人(无 plot 权限)', 'role' => UserRole::Family->value,
        ]);
        FarmMember::create([
            'tenant_id' => $t->id, 'user_id' => $user->id, 'farm_id' => $farm->id,
            'relation' => '家人', 'permission_scope' => ['farm_log'],
        ]);

        $this->actingAs($user)->delete("/family/plots/{$plot->id}")->assertForbidden();

        $this->assertNotSoftDeleted('plots', ['id' => $plot->id]);
    }

    /** 已付款地块对家人是权限边界 → 403（不是后台的 409）。 */
    public function test_family_cannot_delete_paid_plot(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        [$user] = $this->familyMember($t, $farm);

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-FOPAID-01'), ['tenant_id' => $t->id]));
        $this->adoptionOn($plot, $user, AdoptionStatus::Ended, PaymentStatus::Paid);

        $this->actingAs($user)->delete("/family/plots/{$plot->id}")->assertForbidden();

        $this->assertNotSoftDeleted('plots', ['id' => $plot->id]);
    }

    /** 后台列表按可删性分档：可删的给删除按钮，不可删的显示原因。 */
    public function test_admin_plot_index_shows_deletability(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();

        $free = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-UIFREE-01'), ['tenant_id' => $t->id]));
        $paid = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-UIPAID-01'), ['tenant_id' => $t->id]));
        $this->adoptionOn($paid, $admin, AdoptionStatus::Ended, PaymentStatus::Paid);

        // 列表按可删性分档：可删的地块有删除表单，已付款地块只有原因说明
        $this->actingAs($admin)->get('/admin/plots')->assertOk()
            ->assertSee('plots/'.$free->id.'" onsubmit=', false)
            ->assertDontSee('plots/'.$paid->id.'" onsubmit=', false)
            ->assertSee('该地块已有付款记录，无法删除')
            ->assertSee('删除后不可恢复');
    }

    public function test_plot_code_unique_per_tenant(): void
    {
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $admin = $this->admin($t);

        Plot::create(array_merge($this->plotData($farm, $plan, 'FD-DUP-01'), ['tenant_id' => $t->id]));

        $this->actingAs($admin)
            ->post("/admin/plots", $this->plotData($farm, $plan, 'FD-DUP-01'))
            ->assertSessionHasErrors('code');
    }
    public function test_admin_plot_form_pages_render(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-FORM-01'), ['tenant_id' => $t->id]));

        // 新建页与编辑页此前因 formData() 传参错位永远 500，后台无法新增/编辑地块
        $this->actingAs($admin)->get('/admin/plots/create')->assertOk();
        $this->actingAs($admin)->get("/admin/plots/{$plot->id}/edit")->assertOk();
    }

    public function test_admin_can_clear_plot_story(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-STORY-01'), ['tenant_id' => $t->id, 'story' => '原来的故事']));

        // 提交空故事（nullable 字段会被 validate 从结果里移除）此前 500
        $this->actingAs($admin)
            ->post("/admin/plots/{$plot->id}/story", ['story' => ''])
            ->assertRedirect();

        $this->assertNull($plot->fresh()->story);
    }

    // ── R3：地块封面图 ──

    public function test_admin_can_upload_plot_cover(): void
    {
        Storage::fake('public');
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/plots", array_merge($this->plotData($farm, $plan, 'FD-COV-01'), ['cover_image' => $this->cover()]))
            ->assertRedirect();

        $plot = Plot::where('code', 'FD-COV-01')->firstOrFail();
        $this->assertNotNull($plot->cover_image);
        $this->assertTrue(Storage::disk('public')->exists($plot->cover_image));
    }

    public function test_replacing_plot_cover_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();

        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-REPL-01'), ['tenant_id' => $t->id]));
        $plot->attachCover($this->cover());
        $plot->save();
        $oldPath = $plot->fresh()->cover_image;

        $this->actingAs($admin)
            ->put("/admin/plots/{$plot->id}", array_merge($this->plotData($farm, $plan, 'FD-REPL-01'), ['cover_image' => $this->cover()]))
            ->assertRedirect();

        $newPath = $plot->fresh()->cover_image;
        $this->assertNotSame($oldPath, $newPath);
        $this->assertFalse(Storage::disk('public')->exists($oldPath));
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }

    public function test_plot_cover_rejects_non_image(): void
    {
        Storage::fake('public');
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/plots", array_merge($this->plotData($farm, $plan, 'FD-BADIMG-01'), [
                'cover_image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ]))
            ->assertSessionHasErrors('cover_image');

        $this->assertNull(Plot::where('code', 'FD-BADIMG-01')->value('id'));
    }

    public function test_family_can_upload_plot_cover(): void
    {
        Storage::fake('public');
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        [$user] = $this->familyMember($t, $farm);

        $data = $this->plotData($farm, $plan, 'FD-FAMCOV-01');
        unset($data['farm_id']);

        $this->actingAs($user)
            ->post("/family/plots", array_merge($data, ['cover_image' => $this->cover()]))
            ->assertRedirect();

        $plot = Plot::where('code', 'FD-FAMCOV-01')->firstOrFail();
        $this->assertNotNull($plot->cover_image);
        $this->assertSame($farm->id, $plot->farm_id);
    }

    public function test_plot_card_shows_cover_and_placeholder(): void
    {
        Storage::fake('public');
        $t = $this->tenant();
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();

        $withCover = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-SHOW-01'), ['tenant_id' => $t->id]));
        $withCover->attachCover($this->cover());
        $withCover->save();
        Plot::create(array_merge($this->plotData($farm, $plan, 'FD-NOIMG-01'), ['tenant_id' => $t->id]));

        $this->get('/adopt')
            ->assertOk()
            ->assertSee('class="product-card card-portrait"', false)
            ->assertSee(Storage::disk('public')->url($withCover->cover_image))
            ->assertSee('card-cover--placeholder', false)
            ->assertSee('FD-NOIMG-01');
    }

    public function test_plot_forms_expose_the_cover_upload(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $farm = Farm::where('tenant_id', $t->id)->firstOrFail();
        $plan = Plan::where('tenant_id', $t->id)->firstOrFail();
        $plot = Plot::create(array_merge($this->plotData($farm, $plan, 'FD-FORMIMG-01'), ['tenant_id' => $t->id]));

        // 两个表单都缺 enctype 的话图片字段会被静默丢弃：看似保存成功、实际没存。
        foreach (['/admin/plots/create', "/admin/plots/{$plot->id}/edit"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('enctype="multipart/form-data"', false)
                ->assertSee('name="cover_image"', false);
        }

        [$user] = $this->familyMember($t, $farm);
        $this->actingAs($user)->get('/family/plots/create')->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="cover_image"', false);
    }
}
