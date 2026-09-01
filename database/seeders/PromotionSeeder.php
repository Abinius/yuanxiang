<?php

namespace Database\Seeders;

use App\Models\Promotion;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 3.4 促销三件套：老带新（referral）、新客立减（new_customer）、续费抵用（renewal）。
 * 券面额在 rule；推荐码=referral 券的 code。不进 DatabaseSeeder，测试自建数据。
 */
class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();

        $rows = [
            ['type' => 'referral', 'name' => '老带新', 'rule' => ['new_amount' => 300, 'referrer_amount' => 300]],
            ['type' => 'new_customer', 'name' => '新客立减', 'rule' => ['amount' => 300]],
            ['type' => 'renewal', 'name' => '续费抵用', 'rule' => ['amount' => 300]],
        ];

        foreach ($rows as $row) {
            Promotion::updateOrCreate(
                ['tenant_id' => $tenant->id, 'type' => $row['type']],
                ['name' => $row['name'], 'rule' => $row['rule'], 'status' => 'active'],
            );
        }

        $this->command?->info('PromotionSeeder done: referral / new_customer / renewal');
    }
}
