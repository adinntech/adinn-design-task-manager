<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells the mobile app how to reach Reverb (same values the web layout hands to pusher-js), so the
 * app needs no hard-coded socket settings. `enabled` is false when broadcasting isn't Reverb or the
 * key isn't set, in which case the app just keeps polling.
 */
class RealtimeController extends Controller
{
    public const EVENT = 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';

    public function config(Request $request): JsonResponse
    {
        $reverb = config('broadcasting.connections.reverb', []);
        $options = $reverb['options'] ?? [];

        $enabled = config('broadcasting.default') === 'reverb'
            && filled($reverb['key'] ?? null)
            && filled($options['host'] ?? null);

        if (! $enabled) {
            return response()->json(['enabled' => false]);
        }

        return response()->json([
            'enabled' => true,
            'key' => $reverb['key'],
            'host' => $options['host'],
            'port' => (int) ($options['port'] ?? 443),
            'scheme' => $options['scheme'] ?? 'https',
            'channel' => 'private-App.Models.User.'.$request->user()->id,
            'event' => self::EVENT,
        ]);
    }
}
