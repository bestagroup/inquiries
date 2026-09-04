<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'تعداد تلاش‌های ورود بیش از حد مجاز است. '.RateLimiter::availableIn($key).' ثانیه دیگر تلاش کنید.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'ایمیل یا رمز عبور صحیح نیست.']);
        }

        if (! $request->user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'حساب کاربری شما غیرفعال است.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $audit->write('auth.login', $request->user(), [], $request);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $audit->write('auth.logout', $user, [], $request);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
