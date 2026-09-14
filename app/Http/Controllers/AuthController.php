<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GuestPlanClaimService;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, GuestPlanClaimService $claimService)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $request->session()->regenerate();
        $claimed = $claimService->claim($request, $request->user());

        return redirect()->intended(route('home'))->with(
            'status',
            $claimed > 0
                ? "ログインしました。{$claimed}件のGuest計画もこのアカウントに引き継ぎました。"
                : 'ログインしました。'
        );
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request, GuestPlanClaimService $claimService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = User::create($validated);

        // Registration completes the authentication flow. The user should not
        // have to type the same credentials again after protecting Guest data.
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        $claimed = $claimService->claim($request, $user);

        return redirect()->intended(route('home'))->with(
            'status',
            $claimed > 0
                ? "アカウントを作成し、{$claimed}件のGuest計画を保護しました。"
                : 'アカウントを作成しました。これから作る計画はこのアカウントに保存されます。'
        );
    }


    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // Do not reveal whether an address is registered.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return back()->with('status', '登録済みのメールアドレスであれば、再設定用リンクを送信しました。');
        }

        return back()->withErrors(['email' => 'しばらく待ってから、もう一度お試しください。']);
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('auth.login.form')->with('status', 'パスワードを更新しました。新しいパスワードでログインしてください。')
            : back()->withErrors(['email' => __($status)]);
    }

    public function account(Request $request)
    {
        if (! $request->user()) {
            return redirect()->route('auth.login.form');
        }

        return view('auth.account', ['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'ログアウトしました。保護済みの計画は、再ログインすると表示されます。');
    }
}
