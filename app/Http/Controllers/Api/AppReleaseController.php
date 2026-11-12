<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnnounceAppUpdateJob;
use App\Services\AppReleasePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Called by the release pipeline (token-protected, see EnsureReleaseToken): publishes the freshly built APK as a forced
 * update and sends the "Update available" push once.
 */
class AppReleaseController extends Controller
{
    public function store(Request $request, AppReleasePublisher $publisher): JsonResponse
    {
        $data = $request->validate([
            'apk' => ['required', 'file', 'max:204800'], // KB: 200 MB
            'build' => ['required', 'integer', 'min:1'],
            'version' => ['required', 'string', 'max:32', 'regex:/^[0-9A-Za-z.\-+]+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sha256' => ['nullable', 'string', 'size:64'],
            'announce' => ['nullable', 'boolean'],
        ]);

        $release = $publisher->publish(
            $request->file('apk'),
            (int) $data['build'],
            $data['version'],
            $data['notes'] ?? null,
            $data['sha256'] ?? null,
        );

        if ($request->boolean('announce', true)) {
            AnnounceAppUpdateJob::dispatchAfterResponse();
        }

        return response()->json(['ok' => true, 'release' => $release]);
    }
}
