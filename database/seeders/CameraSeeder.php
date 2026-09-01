<?php

namespace Database\Seeders;

use App\Models\Camera;
use App\Models\Farm;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 摄像头 mock：FD-01 online（公开 HLS 测试流）演示播放，FD-02 offline 演示降级。
 * 真实流待 P3（萤石/阿里云），到时后台编辑 stream_url/token 即可，不改码。
 * 不进 DatabaseSeeder，测试自建数据。
 */
class CameraSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();
        $farm = Farm::where('tenant_id', $tenant->id)->firstOrFail();

        $fd01 = Plot::where('tenant_id', $tenant->id)->where('code', 'FD-01')->first();
        $fd02 = Plot::where('tenant_id', $tenant->id)->where('code', 'FD-02')->first();

        $rows = [
            [
                'name' => 'FD-01 田块摄像头',
                'device_no' => 'EZ-MOCK-0001',
                'provider' => 'ezviz',
                'plot_id' => $fd01?->id,
                'stream_url' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
                'playback_url' => null,
                'token' => null,
                'status' => 'online',
            ],
            [
                'name' => 'FD-02 田块摄像头',
                'device_no' => 'EZ-MOCK-0002',
                'provider' => 'ezviz',
                'plot_id' => $fd02?->id,
                'stream_url' => null,
                'playback_url' => null,
                'token' => null,
                'status' => 'offline',
            ],
        ];

        foreach ($rows as $row) {
            Camera::create(array_merge([
                'tenant_id' => $tenant->id,
                'farm_id' => $farm->id,
            ], $row));
        }
    }
}
