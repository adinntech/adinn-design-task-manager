<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no token: the gate must work on the login screen too) feed for the mobile update gate.
 * The app compares its own build number with these values; see config/app_update.php.
 */
class AppUpdateController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $platform = $request->query('platform') === 'android' || ! $request->filled('platform') ? 'android' : null;
        $cfg = $platform ? config("app_update.{$platform}") : null;

        if (! $cfg) {
            return response()->json(['platform' => $request->query('platform'), 'min_build' => 0, 'latest_build' => 0, 'url' => null, 'notes' => null, 'latest_version' => null]);
        }

        return response()->json([
            'platform' => $platform,
            'min_build' => (int) $cfg['min_build'],
            'latest_build' => max((int) $cfg['latest_build'], (int) $cfg['min_build']),
            'latest_version' => $cfg['latest_version'] ?: null,
            'url' => filled($cfg['url']) ? $cfg['url'] : null,
            'notes' => filled($cfg['notes']) ? $cfg['notes'] : null,
        ]);
    }
}
