<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleSheetsClient;
use App\Services\LiveSheetSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin → Task Monitoring → Daily Report → "Live Excel" panel: connect a
 * Google Sheet, sync now, or turn it off. The automatic sync runs nightly at 23:59. The panel itself is
 * rendered in the Daily Report tab (TaskActivityController).
 */
class LiveSheetController extends Controller
{
    public function connect(Request $request, LiveSheetSyncService $live, GoogleSheetsClient $sheets): JsonResponse
    {
        $data = $request->validate(['sheet' => ['required', 'string', 'max:500']]);

        if (! $sheets->configured()) {
            return response()->json(['ok' => false, 'message' => 'The Google service-account key is not installed on the server yet.'], 422);
        }

        $id = LiveSheetSyncService::parseSpreadsheetId($data['sheet']);
        if ($id === null) {
            return response()->json(['ok' => false, 'message' => 'Paste the Google Sheet link (https://docs.google.com/spreadsheets/d/…) or its ID.'], 422);
        }

        try {
            $settings = $live->connect($id, $request->user()->id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => GoogleSheetsClient::describe($e)], 422);
        }

        return response()->json([
            'ok' => $settings['last_error'] === null,
            'message' => $settings['last_error'] ?? 'Connected to "'.$settings['title'].'" — Daily, Weekly, Monthly, Yearly and AI Insights tabs are filled.',
        ], $settings['last_error'] === null ? 200 : 422);
    }

    /** "Sync now": rewrites all Live Excel tabs immediately (the automatic sync runs nightly at 23:59). */
    public function sync(LiveSheetSyncService $live): JsonResponse
    {
        $ok = $live->sync();

        return response()->json([
            'ok' => $ok,
            'message' => $ok ? 'Google Sheet updated.' : 'Sync failed: '.($live->settings()['last_error'] ?? 'Live Excel is not connected.'),
        ], $ok ? 200 : 422);
    }

    public function disconnect(Request $request, LiveSheetSyncService $live): JsonResponse
    {
        $live->disconnect($request->user()->id);

        return response()->json(['ok' => true, 'message' => 'Live Excel turned off. The Google Sheet keeps what it has.']);
    }
}
