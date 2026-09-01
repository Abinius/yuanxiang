<?php

namespace Database\Seeders;

use App\Enums\PlotStatus;
use App\Enums\PlotType;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 田块切分：5 亩 = 50 分地档（FD-01..FD-50，¥5000）
 *          + 1 亩 = 10 拼团田（PT-01..PT-10），每田 30 株（Z-XX-YY，¥300）
 */
class PlotSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();
        $farm = Farm::where('tenant_id', $tenant->id)->firstOrFail();
        $plotPlan = Plan::where('tenant_id', $tenant->id)->where('name', '一分地')->firstOrFail();
        $plantPlan = Plan::where('tenant_id', $tenant->id)->where('name', '单株')->firstOrFail();

        // 5 亩 = 50 分地档
        $plotRows = [];
        for ($i = 1; $i <= 50; $i++) {
            $plotRows[] = [
                'tenant_id' => $tenant->id,
                'farm_id' => $farm->id,
                'plan_id' => $plotPlan->id,
                'parent_plot_id' => null,
                'type' => PlotType::Plot->value,
                'code' => sprintf('FD-%02d', $i),
                'mu_area' => 0.1,
                'price_yearly' => 5000,
                'status' => PlotStatus::Available->value,
                'order_index' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Plot::insert($plotRows);

        // 1 亩 = 10 拼团田，每田 30 株
        for ($g = 1; $g <= 10; $g++) {
            $group = Plot::create([
                'tenant_id' => $tenant->id,
                'farm_id' => $farm->id,
                'plan_id' => null,
                'type' => PlotType::Group->value,
                'code' => sprintf('PT-%02d', $g),
                'mu_area' => 0.1,
                'price_yearly' => null,
                'status' => PlotStatus::Available->value,
                'order_index' => 100 + $g,
            ]);

            $plants = [];
            for ($n = 1; $n <= 30; $n++) {
                $plants[] = [
                    'tenant_id' => $tenant->id,
                    'farm_id' => $farm->id,
                    'plan_id' => $plantPlan->id,
                    'parent_plot_id' => $group->id,
                    'type' => PlotType::Plant->value,
                    'code' => sprintf('Z-%02d-%02d', $g, $n),
                    'mu_area' => 0,
                    'price_yearly' => 300,
                    'status' => PlotStatus::Available->value,
                    'order_index' => $n,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Plot::insert($plants);
        }

        $this->command?->info('PlotSeeder done: 50 分地 + 10 拼团田 + 300 株 = '.Plot::count().' 个认养单元');
    }
}
