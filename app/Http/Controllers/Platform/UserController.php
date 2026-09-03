<?php

namespace App\Http\Controllers\Platform;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 1.6 平台后台账号管理（platform_admin）：商户管理员（绑租户）+ 平台管理员。
 * 超级视角，可跨租户建 tenant_admin；platform_admin 仅平台可建。
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('tenant')
            ->when($request->input('q'), fn ($q, $v) => $q->where(
                fn ($qq) => $qq->where('nickname', 'like', "%{$v}%")
                    ->orWhere('phone', 'like', "%{$v}%")
                    ->orWhere('username', 'like', "%{$v}%")
            ))
            ->when($request->input('role'), fn ($q, $v) => $q->where('role', $v))
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        $tenants = Tenant::orderBy('id')->get(['id', 'slug', 'name']);

        return view('platform.users.index', compact('users', 'tenants'));
    }

    public function create()
    {
        $tenants = Tenant::orderBy('id')->get(['id', 'slug', 'name']);

        return view('platform.users.form', [
            'user' => new User(),
            'tenants' => $tenants,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $user = new User();
        $user->fill($data); // password 走 hashed cast 自动哈希
        $user->tenant_id = $data['role'] === UserRole::PlatformAdmin->value ? null : $data['tenant_id'];
        $user->save();

        return redirect()->route('platform.users.index')->with('ok', "已创建 {$user->nickname}");
    }

    public function edit(User $user)
    {
        $tenants = Tenant::orderBy('id')->get(['id', 'slug', 'name']);

        return view('platform.users.form', [
            'user' => $user,
            'tenants' => $tenants,
        ]);
    }

    public function update(User $user, Request $request)
    {
        $data = $this->validateData($request, $user);
        if (empty($data['password'])) {
            unset($data['password']); // 留空不改密
        }
        $user->fill($data);
        $user->tenant_id = $data['role'] === UserRole::PlatformAdmin->value ? null : $data['tenant_id'];
        $user->save();

        return redirect()->route('platform.users.index')->with('ok', '已更新');
    }

    public function toggle(User $user)
    {
        abort_if($user->id === auth()->id(), 422, '不能禁用自己');

        $user->is_disabled = ! $user->is_disabled;
        $user->save();

        return back()->with('ok', $user->is_disabled ? '已禁用' : '已启用');
    }

    public function resetPassword(User $user, Request $request)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:6']]);
        $user->password = $data['password']; // hashed cast 自动哈希
        $user->save();

        return back()->with('ok', '密码已重置');
    }

    private function validateData(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'nickname' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'regex:/^1\d{10}$/', Rule::unique('users', 'phone')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'max:40', Rule::unique('users', 'username')->ignore($user?->id)],
            'role' => ['required', Rule::in([UserRole::TenantAdmin->value, UserRole::PlatformAdmin->value])],
            'tenant_id' => ['required_unless:role,'.UserRole::PlatformAdmin->value, 'exists:tenants,id'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'birthday' => ['nullable', 'date'],
        ]);
    }
}
