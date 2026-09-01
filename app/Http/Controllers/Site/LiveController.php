<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * 监控嵌入（云乡民视角）：摄像头列表 + 直播/延时/回看播放。
 *
 * 路由 /live，auth 守卫（监控涉家人肖像隐私，不公开）。
 * 路由-param 位置性：方法签名 Tenant 在前、Camera 在后；
 * 因 SubstituteBindings 先于 TenantMiddleware，TenantScope 在绑定时未激活，
 * 故保留显式 tenant_id 守卫。
 * 断流降级：status==='online' && stream_url 走 HLS，否则降级面板。
 * 真实流待 P3 摄像头到位，填 cameras.stream_url/token 即上线，不改码。
 */
class LiveController extends Controller
{
    /** 摄像头列表（在线在前）。 */
    public function index(Tenant $tenant, Request $request)
    {
        $cameras = Camera::query()
            ->where('tenant_id', $tenant->id)
            ->with('plot')
            ->orderByRaw("status='online' desc, id asc")
            ->get();

        return view('site.live.index', [
            'tenant' => $tenant,
            'cameras' => $cameras,
        ]);
    }

    /** 单路播放（HLS）+ 两级断流降级。 */
    public function show(Tenant $tenant, Camera $camera, Request $request)
    {
        abort_if($camera->tenant_id !== $tenant->id, 404);
        $camera->load('plot');

        $streamable = $camera->status === 'online' && filled($camera->stream_url);

        return view('site.live.show', [
            'tenant' => $tenant,
            'camera' => $camera,
            'streamable' => $streamable,
        ]);
    }
}
