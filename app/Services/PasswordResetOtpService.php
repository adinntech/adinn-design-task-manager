<?php

namespace App\Services;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Forgot password" by emailed OTP, shared by the web pages and the mobile API.
 *
 * Callers must never tell the person whether an account matched: issue() is silent when nothing matches
 * (or the account is inactive / has no email), so only a real, active account ever receives a mail.
 */
class PasswordResetOtpService
{
    /** The active account whose username, email or employee code is $login — the same three the sign-in accepts. */
    public function findUser(string $login): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        return User::query()
            ->where(fn ($q) => $q->where('email', $login)->orWhere('username', $login)->orWhere('employee_code', $login))
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->first();
    }

    /** Emails a fresh OTP to the matching account, if there is one. Always returns normally. */
    public function issue(string $login, ?string $ip = null): void
    {
        $user = $this->findUser($login);
        if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $recent = PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', now()->subSeconds((int) config('password-reset.resend_seconds', 60)))
            ->exists();
        if ($recent) {
            return;
        }

        $otp = str_pad((string) random_int(0, (10 ** (int) config('password-reset.otp_length', 6)) - 1), (int) config('password-reset.otp_length', 6), '0', STR_PAD_LEFT);

        $row = DB::transaction(function () use ($user, $otp, $ip) {
            PasswordResetOtp::query()->where('user_id', $user->id)->delete();

            return PasswordResetOtp::query()->create([
                'user_id' => $user->id,
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('password-reset.otp_ttl_minutes', 10)),
                'requested_ip' => $ip,
            ]);
        });

        try {
            $this->send($user, $otp, $row);
        } catch (Throwable $e) {
            // The OTP is useless if it never reached the person — drop it so they can request another at once.
            $row->delete();
            Log::error('Password reset OTP mail failed', ['user_id' => $user->id, 'error' => Str::limit($e->getMessage(), 300)]);
        }
    }

    /**
     * Sets the new password when the OTP is right.
     *
     * @return array{field: string, message: string}|null null on success, otherwise the field to blame and why
     */
    public function reset(string $login, string $otp, string $newPassword): ?array
    {
        $generic = ['field' => 'otp', 'message' => 'This OTP is invalid or has expired. Request a new one.'];

        $user = $this->findUser($login);
        if (! $user) {
            return $generic;
        }

        $row = PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
        if (! $row) {
            return $generic;
        }

        if ($row->attempts >= (int) config('password-reset.max_attempts', 5)) {
            $row->delete();

            return ['field' => 'otp', 'message' => 'Too many incorrect attempts. Request a new OTP.'];
        }

        if (! Hash::check(trim($otp), $row->otp_hash)) {
            $row->increment('attempts');

            return ['field' => 'otp', 'message' => 'Incorrect OTP. Check the mail and try again.'];
        }

        if (Hash::check($newPassword, $user->password)) {
            return ['field' => 'password', 'message' => 'The new password must be different from your current password.'];
        }

        DB::transaction(function () use ($user, $newPassword) {
            $user->update(['password' => Hash::make($newPassword)]);
            PasswordResetOtp::query()->where('user_id', $user->id)->delete();

            // A forgotten password may mean someone else holds a session: sign every device out.
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        return null;
    }

    private function send(User $user, string $otp, PasswordResetOtp $row): void
    {
        $minutes = (int) config('password-reset.otp_ttl_minutes', 10);
        $url = (string) config('password-reset.api_url');

        if ($url === '') {
            // No mail API configured: use Laravel's own mailer with the same look.
            Mail::html(
                view('emails.password-reset-otp', ['name' => $user->name, 'code' => $user->employee_code, 'otp' => $otp, 'minutes' => $minutes])->render(),
                fn ($m) => $m->to($user->email)->subject('Your password reset OTP - Design Task Manager')
            );

            return;
        }

        $request = Http::timeout((int) config('password-reset.api_timeout', 20))->acceptJson();
        if ((string) config('password-reset.api_token') !== '') {
            $request = $request->withHeaders(['X-API-Token' => (string) config('password-reset.api_token')]);
        }

        $response = $request->post($url, [
            'mailtype' => (string) config('password-reset.mailtype'),
            'to' => $user->email,
            'employee_name' => $user->name,
            'employee_code' => (string) ($user->employee_code ?? ''),
            'otp' => $otp,
            'expires_in_minutes' => $minutes,
            'idempotency_key' => 'password-reset-otp-'.$row->id,
        ]);

        if (! $response->successful() || $response->json('status') !== 'success') {
            throw new \RuntimeException('Mail API said: '.Str::limit((string) ($response->json('message') ?? $response->body()), 200));
        }
    }
}
