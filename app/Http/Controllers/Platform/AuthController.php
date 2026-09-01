<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('platform.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $account = $data['account'];
        $field = str_contains($account, '@')
            ? 'email'
            : (preg_match('/^1\d{10}$/', $account) ? 'phone' : 'username');

        $ok = Auth::attempt([
            $field => $account,
            'password' => $data['password'],
            'role' => 'platform_admin',
        ]);

        if (! $ok) {
            throw ValidationException::withMessages(['account' => '账号或密码错误']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('platform.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
