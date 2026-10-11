<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 开发/演示用全套数据的一条入口：`php artisan migrate:fresh --seed` 只跑这个。
 * 顺序有依赖：先租户与基地，再地块，再溯源，最后账号（账号 seeder 会按 slug 找 guangcai 租户）。
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BaseSeeder::class,       // 组织、租户、基地、方案
            PlotSeeder::class,       // 360 个地块
            FarmLogSeeder::class,    // 农事动态
            TraceSeeder::class,      // 有机肥批次 + 采收
            TraceCodeSeeder::class,  // 一箱一码（依赖上一步的批次与采收）
            DemoUserSeeder::class,   // 云乡民 / 家人 + 认养关系
            PromotionSeeder::class,  // 优惠券
            CameraSeeder::class,     // 摄像头
            AdminSeeder::class,      // 平台管理员 / 租户管理员
        ]);
    }
}
