<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmMember;
use App\Models\User;
use App\Support\FamilyScope;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 1.6 商户后台账号管理（tenant_admin）：本租户家人/云乡民/商户子管理员。
 * 不能动 platform_admin；不能跨租户。支持建号/禁用/启用/重置密码。
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('farmMemberships')
            ->where('tenant_id', Tenant::current()->id)
            ->where('role', '!=', UserRole::PlatformAdmin->value)
            ->when($request->input('q'), fn ($q, $v) => $q->where(
                fn ($qq) => $qq->where('nickname', 'like', "%{$v}%")
                    ->orWhere('phone', 'like', "%{$v}%")
                    ->orWhere('username', 'like', "%{$v}%")
            ))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(),
            'roles' => $this->assignableRoles(),
            'member' => new FarmMember(),
            'scopes' => FamilyScope::labels(),
            'relations' => FamilyScope::relations(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $user = new User();
        $user->tenant_id = Tenant::current()->id;
        $user->fill($data); // password 走 hashed cast 自动哈希
        $user->save();

        $this->syncFamilyMember($user, $data);

        return redirect()->route('tenant.admin.users.index', [])
            ->with('ok', "已创建 {$user->nickname}（{$user->role->label()}）");
    }

    public function edit(User $user)
    {
        abort_if($user->role === UserRole::PlatformAdmin, 403, '平台管理员须在平台后台管理');

        return view('admin.users.form', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'member' => $user->farmMemberships()->first() ?? new FarmMember(),
            'scopes' => FamilyScope::labels(),
            'relations' => FamilyScope::relations(),
        ]);
    }

    public function update(User $user, Request $request)
    {
        abort_if($user->role === UserRole::PlatformAdmin, 403);

        $data = $this->validateData($request, $user);
        if (empty($data['password'])) {
            unset($data['password']); // 留空不改密
        }
        $user->fill($data)->save();

        $this->syncFamilyMember($user, $data);

        return redirect()->route('tenant.admin.users.index', [])
            ->with('ok', '已更新');
    }

    /** 禁用/启用（可恢复，不改 role）。 */
    public function toggle(User $user)
    {
        abort_if($user->id === auth()->id(), 422, '不能禁用自己');
        abort_if($user->role === UserRole::PlatformAdmin, 403);

        $user->is_disabled = ! $user->is_disabled;
        $user->save();

        return back()->with('ok', $user->is_disabled ? '已禁用' : '已启用');
    }

    /** 重置密码（不展示，运营告知用户）。 */
    public function resetPassword(User $user, Request $request)
    {
        abort_if($user->role === UserRole::PlatformAdmin, 403);

        $data = $request->validate(['password' => ['required', 'string', 'min:6']]);
        $user->password = $data['password']; // hashed cast 自动哈希
        $user->save();

        return back()->with('ok', '密码已重置');
    }

    private function assignableRoles(): array
    {
        // 商户后台只能建这三类，不含 platform_admin
        return [
            UserRole::Villager->value => '云乡民',
            UserRole::Family->value => '家人',
            UserRole::TenantAdmin->value => '商户管理员',
        ];
    }

    private function validateData(Request $request, ?User $user = null): array
    {
        $roles = array_keys($this->assignableRoles());

        return $request->validate([
            'nickname' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'regex:/^1\d{10}$/', Rule::unique('users', 'phone')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'max:40', Rule::unique('users', 'username')->ignore($user?->id)],
            'role' => ['required', Rule::in($roles)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'birthday' => ['nullable', 'date'],
            'relation' => ['sometimes', 'string', Rule::in(array_keys(FamilyScope::relations()))],
            'permission_scope' => ['sometimes', 'array'],
            'permission_scope.*' => ['sometimes', 'string', Rule::in(FamilyScope::ALL)],
        ]);
    }

    /**
     * 家人账号必须有 farm_members 记录才能在家人端录入；非家人角色不同步，
     * 改角色时保留原记录，改回家人不丢已配权限。
     */
    private function syncFamilyMember(User $user, array $data): void
    {
        if ($user->role !== UserRole::Family) {
            return;
        }

        FarmMember::updateOrCreate(
            ['user_id' => $user->id, 'farm_id' => Farm::firstOrFail()->id],
            [
                'tenant_id' => Tenant::current()->id,
                'relation' => $data['relation'] ?? 'father',
                'permission_scope' => $data['permission_scope'] ?? [],
            ],
        );
    }
}
