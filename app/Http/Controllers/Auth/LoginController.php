<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\Tenant;
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

    public function show()
    {
        return view('auth.login', []);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
            'agreed' => ['accepted'],
        ]);

        $account = $credentials['account'];
        $field = str_contains($account, '@')
            ? 'email'
            : (preg_match('/^1\d{10}$/', $account) ? 'phone' : 'username');

        $ok = Auth::attempt([
            $field => $account,
            'password' => $credentials['password'],
            'tenant_id' => Tenant::current()->id,
            'is_disabled' => false,
        ], (bool) $request->boolean('remember'));

        if (! $ok) {
            throw ValidationException::withMessages(['account' => '账号或密码错误']);
        }

        $this->acceptAgreement($request->user());
        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($request->user()));
    }

    public function wechat()
    {
        if ($this->wechat->mockEnabled()) {
            $mock = $this->wechat->mockUser();
            $user = User::firstOrCreate(
                ['openid' => $mock['openid']],
                ['tenant_id' => Tenant::current()->id, 'nickname' => $mock['nickname'], 'role' => UserRole::Villager->value]
            );

            abort_if($user->is_disabled, 403, '该账号已被停用，请联系客服');

            return $this->loginCrossTenantGuarded($user);
        }

        return redirect()->away($this->wechat->authorizeUrl(Tenant::current()));
    }

    public function wechatCallback(Request $request)
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
                'tenant_id' => Tenant::current()->id,
                'nickname' => $wx['nickname'],
                'unionid' => $wx['unionid'],
                'role' => UserRole::Villager->value,
            ]
        );

        abort_if($user->is_disabled, 403, '该账号已被停用，请联系客服');

        return $this->loginCrossTenantGuarded($user);
    }

    public function bindPhone(Request $request)
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

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('tenant.home', []));
    }

    /**
     * 登录后按角色跳转：单租户下 users 已带 tenant_id，无需再判跨租户上下文。
     * openid 会复用已存在的账号（含家人/管理员身份），所以停用拦截要在各登录入口做。
     */
    private function loginCrossTenantGuarded(User $user): RedirectResponse
    {
        Auth::login($user);
        request()->session()->regenerate();

        $this->acceptAgreement($user);

        return redirect($this->homeFor($user));
    }

    /** 首次同意《服务协议》《隐私政策》即留痕（登录勾选与微信授权同义）。 */
    private function acceptAgreement(User $user): void
    {
        if ($user->agreement_accepted_at === null) {
            $user->forceFill(['agreement_accepted_at' => now()])->save();
        }
    }

    /**
     * 按角色决定登录后落点：云乡民→前台；家人/租户管理员→对应后台；平台管理员→平台后台。
     */
    private function homeFor(User $user): string
    {
        return match ($user->role) {
            UserRole::TenantAdmin => route('tenant.admin.dashboard', []),
            UserRole::Family => route('tenant.family.dashboard', []),
            UserRole::PlatformAdmin => route('platform.dashboard'),
            default => route('tenant.home', []),
        };
    }
}
