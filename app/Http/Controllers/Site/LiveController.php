<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use Illuminate\Http\Request;

/**
 * 监控嵌入（云乡民视角）：摄像头列表 + 直播/延时/回看播放。
 *
 * 路由 /live，auth 守卫（监控涉家人肖像隐私，不公开）。
 * 断流降级：status==='online' && stream_url 走 HLS，否则降级面板。
 * 真实流待 P3 摄像头到位，填 cameras.stream_url/token 即上线，不改码。
 */
class LiveController extends Controller
{
    /** 摄像头列表（在线在前）。 */
    public function index(Request $request)
    {
        $cameras = Camera::query()
            ->with('plot')
            ->orderByRaw("status='online' desc, id asc")
            ->get();

        return view('site.live.index', [
            'cameras' => $cameras,
        ]);
    }

    /** 单路播放（HLS）+ 两级断流降级。 */
    public function show(Camera $camera, Request $request)
    {
        $camera->load('plot');

        $streamable = $camera->status === 'online' && filled($camera->stream_url);

        return view('site.live.show', [
            'camera' => $camera,
            'streamable' => $streamable,
        ]);
    }
}
