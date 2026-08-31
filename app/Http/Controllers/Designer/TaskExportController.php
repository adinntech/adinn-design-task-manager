<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use App\Services\DesignTaskExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same report structure as Designer Head's export (via the shared
 * DesignTaskExportService), forced to this Designer's own tasks only.
 */
class TaskExportController extends Controller
{
    public function export(Request $request, DesignTaskExportService $exportService): StreamedResponse
    {
        abort_unless($request->user()?->role === 'designer', 403);

        $filters = [
            'search' => (string) $request->query('search', ''),
            'vertical' => (string) $request->query('vertical', ''),
            'priority' => (string) $request->query('priority', ''),
            'designerId' => (string) $request->user()->id,
            'bdId' => (string) $request->query('bd_id', ''),
            'period' => (string) $request->query('period', 'current_month'),
            'dateFrom' => (string) $request->query('date_from', ''),
            'dateTo' => (string) $request->query('date_to', ''),
        ];

        return $exportService->export($filters, 'designer-tasks');
    }
}
