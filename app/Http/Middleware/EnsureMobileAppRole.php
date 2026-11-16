<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API only: refuses roles outside config('mobile.allowed_roles').
 *
 * Sign-in already refuses them; this covers a token issued before the restriction. It answers
 * 401 and deletes the token, so the app signs the user out by itself (the same path as an
 * expired session) and their phone stops receiving push notifications.
 */
class EnsureMobileAppRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! in_array($user->role, config('mobile.allowed_roles', []), true)) {
            $token = $user->currentAccessToken();

            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            DeviceToken::query()->where('user_id', $user->id)->delete();

            return response()->json([
                'message' => config('mobile.blocked_message'),
                'code' => 'role_not_allowed',
            ], 401);
        }

        return $next($request);
    }
}
