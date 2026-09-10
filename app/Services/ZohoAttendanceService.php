<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZohoAttendanceService
{
    // Returns true/false for today's check-in status, or null when the
    // status can't be determined (missing token, API error, timeout).
    // Never throws — attendance lookup must not block task creation.
    public function isCheckedIn(?string $email): ?bool
    {
        $token = config('zoho.access_token');

        if (! $email || ! $token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken '.$token,
            ])
                ->timeout(5)
                ->get(config('zoho.attendance_url'), ['emailId' => $email]);

            // Zoho returns HTTP 200 with an {"error": "..."} body (e.g. "Invalid User")
            // for accounts it doesn't recognize — treat that as "unable to check", not false.
            if (! $response->successful() || $response->json('error') !== null || ! is_bool($response->json('isUserAvailable'))) {
                return null;
            }

            return $response->json('isUserAvailable');
        } catch (Throwable $e) {
            Log::warning('Zoho attendance check failed', ['email' => $email, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
