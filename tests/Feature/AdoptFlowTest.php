<?php

namespace Tests\Feature;

use App\Enums\PlotStatus;
use App\Models\Adoption;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdoptFlowTest extends TestCase
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

    private function villager(): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
        ]);
    }

    private function orderData(): array
    {
        return [
            'name' => '张三',
            'phone' => '13800000001',
            'province' => '宁夏',
            'city' => '吴忠',
            'district' => '红寺堡',
            'detail' => '光彩村 1 号',
        ];
    }

    public function test_list_shows_plot_and_group_sections(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("/adopt")
            ->assertOk()
            ->assertSee('分地档')
            ->assertSee('FD-01')
            ->assertSee('株档')
            ->assertSee('PT-01');
    }

    public function test_plot_detail_and_group_page(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();
        $group = Plot::where('type', 'group')->first();

        $this->get("/adopt/{$plot->id}")
            ->assertOk()
            ->assertSee('FD-01')
            ->assertSee('立即认养');

        $this->get("/adopt/{$group->id}")
            ->assertOk()
            ->assertSee('拼团田');
    }

    public function test_guest_order_redirects_to_login(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();

        $this->post("/adopt/{$plot->id}/order", $this->orderData())
            ->assertRedirect("/login");
    }

    public function test_order_creates_pending_adoption_and_payment(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $this->actingAs($user);
        $plot = Plot::where('type', 'plot')->first();

        $this->post("/adopt/{$plot->id}/order", $this->orderData())
            ->assertRedirect();

        $this->assertDatabaseHas('adoptions', [
            'adoptable_id' => $plot->id,
            'status' => 'pending_payment',
            'annual_fee' => 5000,
        ]);
        $this->assertDatabaseHas('payments', ['status' => 'pending']);
        // 地块尚未置已认养（支付/签约后才占）
        $this->assertDatabaseHas('plots', ['id' => $plot->id, 'status' => 'available']);
    }

    public function test_confirm_mock_payment_advances_flow(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $this->actingAs($user);
        $plot = Plot::where('type', 'plot')->first();

        $this->post("/adopt/{$plot->id}/order", $this->orderData());
        $adoption = Adoption::where('adoptable_id', $plot->id)->firstOrFail();

        $this->post("/adopt/order/{$adoption->id}/pay")
            ->assertRedirect();

        $this->assertDatabaseHas('payments', ['payable_id' => $adoption->id, 'status' => 'paid']);
        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'status' => 'pending_agreement']);
    }

    public function test_cannot_order_sold_out_plot(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $this->actingAs($this->villager());
        $plot = Plot::where('type', 'plot')->first();
        $plot->update(['status' => 'sold_out']);

        $this->post("/adopt/{$plot->id}/order", $this->orderData())
            ->assertStatus(422);
    }

    public function test_cannot_order_same_plot_twice(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $this->actingAs($this->villager());
        $plot = Plot::where('type', 'plot')->first();

        $this->post("/adopt/{$plot->id}/order", $this->orderData())->assertRedirect();
        $this->post("/adopt/{$plot->id}/order", $this->orderData())->assertStatus(422);
    }

    public function test_sign_agreement_activates_and_marks_plot_adopted(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $this->actingAs($this->villager());
        $plot = Plot::where('type', 'plot')->first();

        $this->post("/adopt/{$plot->id}/order", $this->orderData());
        $adoption = Adoption::where('adoptable_id', $plot->id)->firstOrFail();
        $this->post("/adopt/order/{$adoption->id}/pay");

        $this->post("/adopt/order/{$adoption->id}/sign", ['named_label' => '阿林的光彩田'])
            ->assertRedirect();

        $this->assertDatabaseHas('adoptions', [
            'id' => $adoption->id,
            'status' => 'active',
            'named_label' => '阿林的光彩田',
        ]);
        $this->assertDatabaseHas('plots', ['id' => $plot->id, 'status' => 'adopted']);
        $adoption->refresh();
        $this->assertNotNull($adoption->agreement_signed_at);
        $this->assertNotNull($adoption->end_date);
    }

    public function test_cannot_sign_from_wrong_state(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $this->actingAs($this->villager());
        $plot = Plot::where('type', 'plot')->first();

        $this->post("/adopt/{$plot->id}/order", $this->orderData());
        $adoption = Adoption::where('adoptable_id', $plot->id)->firstOrFail();
        // 仍处 pending_payment，未模拟支付

        $this->post("/adopt/order/{$adoption->id}/sign", ['named_label' => 'X'])
            ->assertStatus(422);
    }

    public function test_cannot_order_group_directly(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $this->actingAs($this->villager());
        $group = Plot::where('type', 'group')->first();

        $this->post("/adopt/{$group->id}/order", $this->orderData())
            ->assertStatus(422);
    }

    public function test_last_plant_in_group_marks_group_sold_out(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $this->actingAs($this->villager());
        $group = Plot::where('type', 'group')->first();
        $plants = $group->children()->orderBy('order_index')->get();
        $last = $plants->pop();
        $plants->each(fn ($p) => $p->update(['status' => PlotStatus::Adopted->value]));

        $this->post("/adopt/{$last->id}/order", $this->orderData());
        $adoption = Adoption::where('adoptable_id', $last->id)->firstOrFail();
        $this->post("/adopt/order/{$adoption->id}/pay");
        $this->post("/adopt/order/{$adoption->id}/sign", ['named_label' => '末株']);

        $this->assertDatabaseHas('plots', ['id' => $group->id, 'status' => 'sold_out']);
        $this->assertDatabaseHas('plots', ['id' => $last->id, 'status' => 'adopted']);
    }
}
