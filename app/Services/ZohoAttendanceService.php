<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZohoAttendanceService
{
    private const TOKEN_CACHE_KEY = 'zoho.people.access_token';

    private const REFRESH_FAILED_CACHE_KEY = 'zoho.people.refresh_failed';

    // Zoho access tokens live 1 hour; refresh a little early.
    private const TOKEN_TTL_SECONDS = 3300;

    // After a failed refresh, don't hammer Zoho's token endpoint (it rate-limits).
    private const REFRESH_RETRY_AFTER_SECONDS = 120;

    // Returns true/false for today's check-in status, or null when the
    // status can't be determined (missing token, API error, timeout).
    // Never throws — attendance lookup must not block task creation.
    public function isCheckedIn(?string $email): ?bool
    {
        $token = $this->accessToken();

        if (! $email || ! $token) {
            return null;
        }

        try {
            $response = $this->request($email, $token);

            // Expired / invalid access token: mint a new one from the refresh
            // token and retry exactly once.
            if ($this->isTokenRejected($response) && ($fresh = $this->refreshAccessToken($token))) {
                $response = $this->request($email, $fresh);
            }

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

    private function request(string $email, string $token): Response
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$token])
            ->timeout(5)
            ->get(config('zoho.attendance_url'), ['emailId' => $email]);
    }

    /** Cached refreshed token if we have one, otherwise the one from .env. */
    private function accessToken(): ?string
    {
        return Cache::get(self::TOKEN_CACHE_KEY) ?: (config('zoho.access_token') ?: null);
    }

    private function isTokenRejected(Response $response): bool
    {
        return $response->status() === 401
            || (int) $response->json('response.errors.code') === 7213
            || (int) $response->json('errors.code') === 7213;
    }

    /**
     * Exchange the refresh token for a new access token (cached for ~55 min).
     * $rejectedToken is the token that just failed: if another request already
     * refreshed while we waited for the lock, reuse that instead of refreshing again.
     */
    private function refreshAccessToken(string $rejectedToken): ?string
    {
        $refreshToken = config('zoho.refresh_token');
        $clientId = config('zoho.client_id');
        $clientSecret = config('zoho.client_secret');

        if (! $refreshToken || ! $clientId || ! $clientSecret || Cache::has(self::REFRESH_FAILED_CACHE_KEY)) {
            return null;
        }

        try {
            return Cache::lock('zoho.people.refresh', 10)->block(5, function () use ($rejectedToken, $refreshToken, $clientId, $clientSecret) {
                $current = Cache::get(self::TOKEN_CACHE_KEY);
                if ($current && $current !== $rejectedToken) {
                    return $current;
                }

                $response = Http::asForm()->timeout(8)->post(rtrim((string) config('zoho.accounts_url'), '/').'/oauth/v2/token', [
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                ]);

                $newToken = $response->json('access_token');

                if (! $response->successful() || ! is_string($newToken) || $newToken === '') {
                    Cache::put(self::REFRESH_FAILED_CACHE_KEY, true, self::REFRESH_RETRY_AFTER_SECONDS);
                    Log::warning('Zoho token refresh failed', [
                        'status' => $response->status(),
                        'error' => $response->json('error'),
                    ]);

                    return null;
                }

                Cache::put(self::TOKEN_CACHE_KEY, $newToken, self::TOKEN_TTL_SECONDS);

                return $newToken;
            });
        } catch (Throwable $e) {
            Log::warning('Zoho token refresh failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
