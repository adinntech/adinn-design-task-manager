<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Mobile "Forgot password" — the same rules and mail as the web pages (PasswordResetOtpService). Public routes. */
class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetOtpService $otps) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190']]);

        $this->otps->issue($data['login'], $request->ip());

        // Same answer whether or not an account matched.
        return response()->json(['message' => 'If these details match an account, a 6-digit OTP has been sent to its registered email.']);
    }

    public function reset(Request $request): JsonResponse
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
            throw ValidationException::withMessages([$error['field'] => $error['message']]);
        }

        return response()->json(['message' => 'Password reset successfully. Sign in with your new password.']);
    }
}
