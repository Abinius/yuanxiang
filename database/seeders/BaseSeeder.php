<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 基础数据：运营主体（花乌巷食品/青狐互动）+ 首租户（光彩云村庄）
 * + 基地 + 认养方案（一分地/单株，丰欠共担/保底细则见 DB 设计 §4.1）
 */
class BaseSeeder extends Seeder
{
    public function run(): void
    {
        $hua = Organization::create([
            'name' => '宁夏花乌巷食品有限公司',
            'role' => 'tenant',
            'wx_mch_id' => '待核实',
            'food_license_no' => '待核实',
        ]);

        Organization::create([
            'name' => '西安青狐互动文化传媒有限公司',
            'role' => 'content',
        ]);

        $tenant = Tenant::create([
            'slug' => 'guangcai',
            'name' => '光彩云村庄',
            'operator_org_id' => $hua->id,
            'status' => 'active',
            'settings' => ['brand' => ['primary' => '#B33A26', 'accent' => '#C9A227']],
        ]);

        $farm = Farm::create([
            'tenant_id' => $tenant->id,
            'operator_org_id' => $hua->id,
            'name' => '光彩村基地',
            'region' => '宁夏红寺堡',
            'country' => '中国',
            'cert_status' => 'not_started',
        ]);

        // 一分地（主力）：丰欠共担/保底细则
        Plan::create([
            'tenant_id' => $tenant->id,
            'name' => '一分地',
            'subject_type' => 'plot',
            'price_yearly' => 5000,
            'delivery_rule' => [
                'guarantee_kg' => 15,
                'baseline_kg' => 20,
                'cap_kg' => 25,
                'over_bonus_ratio' => 0.5,
                'shortfall' => [
                    'compensate_to_kg' => 15,
                    'refund_price_kg' => 150,
                    'severe_threshold_kg' => 10,
                    'severe_action' => 'refund_prorated + next_season_priority',
                ],
                'force_majeure' => 'refund_or_defer',
                'quality' => ['grade_floor' => '一级', 'fail_action' => 'replace_or_refund'],
            ],
            'benefits' => ['naming', 'monitor', 'trace', 'gift_quota', 'village_card'],
            'festival_quota' => ['spring' => 1, 'dragon_boat' => 1, 'mid_autumn' => 1],
            'stock_mode' => 'quota',
        ]);

        // 单株（轻量入口）：拼团田池均摊
        Plan::create([
            'tenant_id' => $tenant->id,
            'name' => '单株',
            'subject_type' => 'plant',
            'price_yearly' => 300,
            'delivery_rule' => ['guarantee_kg' => 0.5, 'pool_mode' => true],
            'benefits' => ['naming', 'monitor', 'trace'],
            'stock_mode' => 'quota',
        ]);

        $this->command?->info("BaseSeeder done: tenant={$tenant->slug} farm={$farm->name} plans=一分地/单株");
    }
}
