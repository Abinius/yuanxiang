<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 管理账号（幂等）：平台管理员 + 首租户管理员。开发期密码 test1234。
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();

        User::firstOrCreate(
            ['username' => 'platform'],
            ['role' => UserRole::PlatformAdmin->value, 'nickname' => '平台管理员', 'password' => 'test1234']
        );

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'tenant_id' => $tenant->id,
                'role' => UserRole::TenantAdmin->value,
                'nickname' => '阿林（管理）',
                'password' => 'test1234',
            ]
        );

        $this->command?->info('AdminSeeder done: platform/admin 密码 test1234');
    }
}
