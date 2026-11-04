<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a push to every mobile device a user registered (device_tokens) through FCM HTTP v1.
 * Like WebPushService it never throws: a push failure must never break the workflow action that
 * triggered it. Tokens FCM reports as unregistered/invalid are removed. Honours the same
 * "Web Push Notifications" profile switch as the browser push.
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param  array<string, scalar|null>  $data  Extra key/values for the app (task_id, url, ...); stringified for FCM.
     * @return int Devices the push was accepted for.
     */
    public function send(User $user, string $title, string $body, ?string $url = null, ?string $tag = null, array $data = []): int
    {
        try {
            if ($user->web_push_enabled === false || ! in_array($user->role, config('webpush.roles', []), true)) {
                return 0;
            }

            $credentials = $this->credentials();
            if ($credentials === null) {
                return 0;
            }

            $tokens = DeviceToken::query()->where('user_id', $user->id)->get();
            if ($tokens->isEmpty()) {
                return 0;
            }

            $accessToken = $this->accessToken($credentials);
            $project = config('fcm.project_id') ?: ($credentials['project_id'] ?? null);
            if (! $accessToken || ! $project) {
                return 0;
            }

            $payloadData = array_map('strval', array_filter(
                $data + ['url' => $url, 'tag' => $tag],
                fn ($v) => $v !== null
            ));

            $delivered = 0;
            foreach ($tokens as $device) {
                $message = [
                    'token' => $device->token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $payloadData,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => array_filter(['tag' => $tag, 'channel_id' => 'tasks']),
                    ],
                ];

                $response = Http::withToken($accessToken)
                    ->timeout((int) config('fcm.timeout', 8))
                    ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", ['message' => $message]);

                if ($response->successful()) {
                    $delivered++;
                    $device->update(['last_seen_at' => now()]);

                    continue;
                }

                $status = (string) $response->json('error.status');
                $detail = (string) $response->json('error.details.0.errorCode');

                if ($response->status() === 404 || $status === 'NOT_FOUND' || $detail === 'UNREGISTERED' || $status === 'INVALID_ARGUMENT') {
                    $device->delete();
                } else {
                    Log::error('FCM push failed', ['user_id' => $user->id, 'status' => $response->status(), 'error' => $response->json('error.message')]);
                }
            }

            return $delivered;
        } catch (Throwable $e) {
            Log::error('FCM dispatch failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    /** @return array<string, mixed>|null service-account JSON, or null when mobile push isn't configured */
    private function credentials(): ?array
    {
        $raw = config('fcm.credentials_json');

        if (! filled($raw) && filled($path = config('fcm.credentials')) && is_readable($path)) {
            $raw = file_get_contents($path);
        }

        $json = filled($raw) ? json_decode((string) $raw, true) : null;

        return is_array($json) && ! empty($json['client_email']) && ! empty($json['private_key']) ? $json : null;
    }

    /** OAuth2 access token for the service account (RS256 JWT bearer grant), cached until just before expiry. */
    private function accessToken(array $credentials): ?string
    {
        return Cache::remember('fcm:access-token:'.md5($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $segments = [
                $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->b64(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            $signature = '';
            if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }
            $segments[] = $this->b64($signature);

            $response = Http::asForm()->timeout((int) config('fcm.timeout', 8))
                ->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => implode('.', $segments),
                ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
