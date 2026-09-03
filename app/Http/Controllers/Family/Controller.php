<?php

namespace App\Http\Controllers\Family;

use App\Enums\UserRole;
use App\Http\Controllers\Controller as FrameworkController;
use App\Models\Farm;
use App\Models\FarmMember;
use Illuminate\Http\Request;

/**
 * 家人端控制器基类：统一 scope 限权守卫。
 *
 * - tenant_admin 直通全部 scope（无 farm_member 也可录）；
 * - family 角色按 farm_members.permission_scope 限权，无 membership 或 scope 缺失 → 403。
 * 路由-param 位置性：子类方法签名 Tenant 在前、其它绑定在后。
 */
class Controller extends FrameworkController
{
    /** 当前 user 在本租户基地的 membership（可能为 null）。 */
    protected function currentFarmMember(Request $request): ?FarmMember
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        return FarmMember::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();
    }

    /**
     * 断言当前 user 拥有指定 scope，返回可用的 FarmMember 上下文。
     * tenant_admin 直通：返回一个（未持久化的）临时代理，携带 farm_id/user_id。
     */
    protected function assertScope(Request $request, string $scope): FarmMember
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        if ($user->role === UserRole::TenantAdmin) {
            $farm = Farm::where('tenant_id', $tenant->id)->firstOrFail();

            return new FarmMember([
                'tenant_id' => $tenant->id,
                'farm_id' => $farm->id,
                'user_id' => $user->id,
                'relation' => 'tenant_admin',
                'permission_scope' => ['farm_log', 'fertilizer', 'harvest', 'plot'],
            ]);
        }

        $member = $this->currentFarmMember($request);
        abort_if($member === null, 403, '非本基地家人，无录入权限');
        abort_unless(in_array($scope, $member->permission_scope ?? [], true), 403, '未授权该录入');

        return $member;
    }
}
