<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\Farm;
use App\Models\Plot;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 摄像头后台管理（tenant_admin）。P3 硬件到位后在此填真实流地址/凭证。
 * 路由-param 位置性：Tenant 在前、Camera 在后；显式 tenant_id 守卫。
 */
class CameraController extends Controller
{
    public function index(Request $request)
    {
        $cameras = Camera::query()
            ->with('plot')
            ->orderByDesc('id')
            ->get();

        return view('admin.cameras.index', compact('cameras'));
    }

    public function create(Request $request)
    {
        $plots = Plot::orderBy('code')->get();

        return view('admin.cameras.form', [
            'camera' => new Camera(),
            'plots' => $plots,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, Tenant::current());
        $farm = Farm::firstOrFail();

        $camera = new Camera($data);
        $camera->tenant_id = Tenant::current()->id;
        $camera->farm_id = $farm->id;
        $camera->save();

        return redirect()->route('tenant.admin.cameras.index', [])
            ->with('ok', '摄像头已添加');
    }

    public function edit(Camera $camera, Request $request)
    {
        $plots = Plot::orderBy('code')->get();

        return view('admin.cameras.form', compact('camera', 'plots'));
    }

    public function update(Camera $camera, Request $request)
    {
        $data = $this->validateData($request, Tenant::current());
        $camera->fill($data)->save();

        return redirect()->route('tenant.admin.cameras.index', [])
            ->with('ok', '已更新');
    }

    public function destroy(Camera $camera, Request $request)
    {
        $camera->delete();

        return redirect()->route('tenant.admin.cameras.index', [])
            ->with('ok', '已删除');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'device_no' => ['required', 'string', 'max:80'],
            'provider' => ['required', 'string', 'max:40'],
            'plot_id' => ['nullable', Rule::exists('plots', 'id')->where('tenant_id', Tenant::current()->id)],
            'stream_url' => ['nullable', 'string', 'max:500'],
            'playback_url' => ['nullable', 'string', 'max:500'],
            'token' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['online', 'offline'])],
        ]);
    }
}
