<?php

namespace Database\Seeders;

use App\Enums\AdoptionStatus;
use App\Models\Farm;
use App\Models\FarmMember;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use Illuminate\Database\Seeder;

/**
 * 演示账号（dev 走查，密码统一 test1234）：
 * - family  家人端：阿叔，全 scope（farm_log/fertilizer/harvest）
 * - villager 用户端：云乡民阿林，带一份已生效认养（我的田/礼盒/续费/配送可用），带 openid（扫码/推送）
 * 幂等：firstOrCreate / updateOrCreate / 已生效则不重复下单。
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();
        $farm = Farm::where('tenant_id', $tenant->id)->firstOrFail();

        $family = User::firstOrCreate(
            ['username' => 'family'],
            [
                'tenant_id' => $tenant->id,
                'phone' => '13900000001',
                'nickname' => '阿叔',
                'role' => 'family',
                'password' => 'test1234',
            ]
        );
        FarmMember::updateOrCreate(
            ['user_id' => $family->id, 'farm_id' => $farm->id],
            ['tenant_id' => $tenant->id, 'relation' => 'father', 'permission_scope' => ['farm_log', 'fertilizer', 'harvest']],
        );

        $villager = User::firstOrCreate(
            ['username' => 'villager'],
            [
                'tenant_id' => $tenant->id,
                'phone' => '13800000001',
                'openid' => 'mock_openid_alin',
                'nickname' => '云乡民阿林',
                'role' => 'villager',
                'password' => 'test1234',
            ]
        );

        if ($villager->adoptions()->where('status', AdoptionStatus::Active)->doesntExist()) {
            $plot = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
            $service = app(AdoptionService::class);
            $adoption = $service->createOrder($villager, $plot, [
                'name' => '阿林', 'phone' => '13800000001', 'province' => '宁夏',
                'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
            ]);
            $service->confirmMockPayment($adoption);
            $service->signAgreement($adoption, '阿林的光彩田');
        }

        $this->command?->info('DemoUserSeeder done: family / villager 密码 test1234');
    }
}
