<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Sends a desktop notification to every browser a user enabled. Never throws:
 * like TaskNotificationService, a push failure must never break the workflow
 * action that triggered it. Browsers that unsubscribed (404/410) are removed.
 */
class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    public function isEnabledFor(?User $user): bool
    {
        return $user !== null && in_array($user->role, config('webpush.roles', []), true);
    }

    /** My Profile "Web Push Notifications" switch; OFF keeps the subscriptions but sends nothing. */
    public function isOnFor(User $user): bool
    {
        return $user->web_push_enabled !== false;
    }

    /**
     * @param  string  $tag  Same tag replaces the previous notification instead of stacking.
     */
    public function send(User $user, string $title, string $body, ?string $url = null, ?string $tag = null, array $data = []): int
    {
        // Mobile app (FCM) gets the same alert; independent of whether browser push is configured.
        app(FcmService::class)->send($user, $title, $body, $url, $tag, $data);

        if (! $this->isConfigured() || ! $this->isEnabledFor($user) || ! $this->isOnFor($user)) {
            return 0;
        }

        try {
            $subscriptions = PushSubscription::query()->where('user_id', $user->id)->get();
            if ($subscriptions->isEmpty()) {
                return 0;
            }

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $url ?? url('/'),
                'tag' => $tag,
                'icon' => asset('images/adinn-logo.png'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Kept for a day if the PC is off/offline, then dropped by the push service.
            $options = ['TTL' => 86400, 'urgency' => 'high'];

            $webPush = $this->client();
            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]), $payload, $options);
            }

            $delivered = 0;
            foreach ($webPush->flush() as $report) {
                $hash = PushSubscription::hashEndpoint($report->getEndpoint());

                if ($report->isSuccess()) {
                    $delivered++;
                    PushSubscription::query()->where('endpoint_hash', $hash)->update(['last_success_at' => now()]);
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', $hash)->delete();
                } else {
                    Log::error('Web push failed', ['user_id' => $user->id, 'reason' => $report->getReason()]);
                }
            }

            return $delivered;
        } catch (Throwable $e) {
            Log::error('Web push dispatch failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    private function client(): WebPush
    {
        $webPush = new WebPush(
            ['VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ]],
            [],
            new Client(['timeout' => (int) config('webpush.timeout', 8)]),
        );
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
