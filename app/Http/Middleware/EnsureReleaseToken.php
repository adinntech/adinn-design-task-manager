<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets only the release pipeline through: Authorization: Bearer <RELEASE_UPLOAD_TOKEN>. Without that .env key the endpoint is off (404). */
class EnsureReleaseToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('app_update.release_token');

        abort_if($expected === '', 404);
        abort_unless(hash_equals($expected, (string) $request->bearerToken()), 401, 'Invalid release token.');

        return $next($request);
    }
}
