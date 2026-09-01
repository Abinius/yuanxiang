<?php

namespace Database\Seeders;

use App\Enums\FarmLogType;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 给若干分地块撒农事动态，供 dev serve 时"我的田"非空。
 * 真实数据由家人端录入（2.4 后），本 seeder 仅演示。
 */
class FarmLogSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();
        $farm = Farm::where('tenant_id', $tenant->id)->firstOrFail();

        $plots = Plot::where('tenant_id', $tenant->id)
            ->where('type', 'plot')
            ->orderBy('id')
            ->take(3)
            ->get();

        $records = [
            ['type' => FarmLogType::Prune,     'title' => '春剪定型',   'content' => '春剪老眼枝、留壮枝，树形定型。',         'month' => 3, 'day' => 15],
            ['type' => FarmLogType::Fertilize, 'title' => '有机肥基施', 'content' => 'NXLB 有机肥开沟基施，每株约 2kg。',      'month' => 4, 'day' => 5],
            ['type' => FarmLogType::Weed,      'title' => '行间除草',   'content' => '人工拔除行间杂草，避免伤根。',           'month' => 5, 'day' => 20],
            ['type' => FarmLogType::Daily,     'title' => '巡田记录',   'content' => '长势良好，未见病虫害。',                 'month' => 6, 'day' => 10],
            ['type' => FarmLogType::Harvest,   'title' => '夏果首采',   'content' => '夏果成熟，首采晾晒入帘。',               'month' => 8, 'day' => 1],
        ];

        $year = now()->year;

        foreach ($plots as $plot) {
            foreach ($records as $r) {
                FarmLog::create([
                    'tenant_id' => $tenant->id,
                    'farm_id' => $farm->id,
                    'plot_id' => $plot->id,
                    'type' => $r['type']->value,
                    'title' => $r['title'],
                    'content' => $r['content'],
                    'occurred_at' => now()->setDate($year, $r['month'], $r['day'])->startOfDay(),
                    'is_public' => true,
                    'source' => 'family',
                ]);
            }
        }
    }
}
