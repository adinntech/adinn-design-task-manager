<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Saves / removes the current browser's Web Push subscription. A browser
 * belongs to whoever is logged in on it last, so re-subscribing after another
 * user logs in moves the row to the new user.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request, WebPushService $push): JsonResponse
    {
        abort_unless($push->isEnabledFor($request->user()), 403);

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint', '');

        if ($endpoint !== '') {
            PushSubscription::query()
                ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
                ->where('user_id', $request->user()->id)
                ->delete();
        }

        return response()->json(['status' => 'unsubscribed']);
    }

    /** "Send test" button: proves the whole chain (browser → push service → this PC). */
    public function test(Request $request, WebPushService $push): JsonResponse
    {
        $delivered = $push->send(
            $request->user(),
            'Desktop notifications are on',
            'You will get task alerts from the Design Task Manager here.',
            url('/'),
            'push-test'
        );

        return response()->json(['delivered' => $delivered]);
    }
}
