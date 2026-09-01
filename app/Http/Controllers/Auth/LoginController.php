<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WechatOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * 双登录：账号密码（phone/username/email）+ 微信一键（openid）。
 * 两路都归一到 users，微信用户可「绑定手机号」升级为双登录。
 */
class LoginController extends Controller
{
    public function __construct(private readonly WechatOAuth $wechat)
    {
    }

    public function show(Tenant $tenant)
    {
        return view('auth.login', ['tenant' => $tenant]);
    }

    public function login(Request $request, Tenant $tenant)
    {
        $credentials = $request->validate([
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $account = $credentials['account'];
        $field = str_contains($account, '@')
            ? 'email'
            : (preg_match('/^1\d{10}$/', $account) ? 'phone' : 'username');

        $ok = Auth::attempt([
            $field => $account,
            'password' => $credentials['password'],
            'tenant_id' => $tenant->id,
        ], (bool) $request->boolean('remember'));

        if (! $ok) {
            throw ValidationException::withMessages(['account' => '账号或密码错误']);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($request->user(), $tenant));
    }

    public function wechat(Tenant $tenant)
    {
        if ($this->wechat->mockEnabled()) {
            $mock = $this->wechat->mockUser();
            $user = User::firstOrCreate(
                ['openid' => $mock['openid']],
                ['tenant_id' => $tenant->id, 'nickname' => $mock['nickname'], 'role' => UserRole::Villager->value]
            );

            return $this->loginCrossTenantGuarded($user, $tenant);
        }

        return redirect()->away($this->wechat->authorizeUrl($tenant));
    }

    public function wechatCallback(Request $request, Tenant $tenant)
    {
        abort_unless(
            $request->state && hash_equals((string) session('wechat_state'), (string) $request->state),
            419,
            'state 校验失败，请重试'
        );

        $wx = $this->wechat->userByCode($request->code);
        $user = User::firstOrCreate(
            ['openid' => $wx['openid']],
            [
                'tenant_id' => $tenant->id,
                'nickname' => $wx['nickname'],
                'unionid' => $wx['unionid'],
                'role' => UserRole::Villager->value,
            ]
        );

        return $this->loginCrossTenantGuarded($user, $tenant);
    }

    public function bindPhone(Request $request, Tenant $tenant)
    {
        $request->validate(['phone' => ['required', 'regex:/^1\d{10}$/']]);

        $user = $request->user();
        $existing = User::where('phone', $request->phone)->where('id', '!=', $user->id)->first();
        abort_if($existing, 422, '该手机号已绑定其他账号');

        $user->update([
            'phone' => $request->phone,
            'password' => $request->filled('password') ? $request->password : $user->password,
            'password_set_at' => $request->filled('password') ? now() : $user->password_set_at,
        ]);

        return back()->with('status', '绑定成功，现在可用账号密码登录');
    }

    public function logout(Request $request, Tenant $tenant)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('tenant.home', ['tenant' => $tenant->slug]));
    }

    /**
     * 微信登录跨租户守卫：openid 已属于其他云村庄时不在本租户自动登录，
     * 防「以他租户身份进入本租户上下文」打通跨租户越权（P0 修复）。
     */
    private function loginCrossTenantGuarded(User $user, Tenant $tenant): RedirectResponse
    {
        abort_if((int) $user->tenant_id !== $tenant->id, 403, '该微信账号已属于其他云村庄，请使用对应村庄登录');

        Auth::login($user);
        request()->session()->regenerate();

        return redirect($this->homeFor($user, $tenant));
    }

    /**
     * 按角色决定登录后落点：云乡民→前台；家人/租户管理员→对应后台；平台管理员→平台后台。
     */
    private function homeFor(User $user, Tenant $tenant): string
    {
        return match ($user->role) {
            UserRole::TenantAdmin => route('tenant.admin.dashboard', ['tenant' => $tenant->slug]),
            UserRole::Family => route('tenant.family.dashboard', ['tenant' => $tenant->slug]),
            UserRole::PlatformAdmin => route('platform.dashboard'),
            default => route('tenant.home', ['tenant' => $tenant->slug]),
        };
    }
}
