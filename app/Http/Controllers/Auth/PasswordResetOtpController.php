<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Web "Forgot password": ask for Username / Email / Employee Code, mail an OTP, then set a new password with it. */
class PasswordResetOtpController extends Controller
{
    public function __construct(private PasswordResetOtpService $otps) {}

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190']]);

        $this->otps->issue($data['login'], $request->ip());

        // Same answer whether or not an account matched, so this page can't be used to look people up.
        $request->session()->put('password_reset.login', trim($data['login']));

        return redirect()->route('password.reset')
            ->with('status', 'If these details match an account, a 6-digit OTP has been sent to its registered email.');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $login = $request->session()->get('password_reset.login');
        if (! $login) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password', ['login' => $login]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'otp' => ['required', 'digits:'.(int) config('password-reset.otp_length', 6)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'otp.digits' => 'Enter the 6-digit OTP from the email.',
        ]);

        $error = $this->otps->reset($data['login'], $data['otp'], $data['password']);
        if ($error !== null) {
            return back()->withInput($request->only('login'))->withErrors([$error['field'] => $error['message']]);
        }

        $request->session()->forget('password_reset.login');

        return redirect()->route('login')->with('status', 'Password reset successfully. Sign in with your new password.');
    }
}
