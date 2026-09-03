<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Services\DesignTaskExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same report structure as Designer Head's export (via the shared
 * DesignTaskExportService), forced to this BD's own created/assigned
 * tasks only. Designer filter is allowed (scoped to this BD's own tasks
 * by the view's designer dropdown), BD selector is not exposed.
 */
class TaskExportController extends Controller
{
    public function export(Request $request, DesignTaskExportService $exportService): StreamedResponse
    {
        abort_unless($request->user()?->role === 'bd', 403);

        $priority = (string) $request->query('priority', '');
        $isOverdue = $priority === 'overdue';

        $filters = [
            'search' => (string) $request->query('search', ''),
            'vertical' => (string) $request->query('vertical', ''),
            'priority' => $isOverdue ? '' : $priority,
            'designerId' => (string) $request->query('designer_id', ''),
            'bdId' => (string) $request->user()->id,
            'period' => (string) $request->query('period', 'current_month'),
            'dateFrom' => (string) $request->query('date_from', ''),
            'dateTo' => (string) $request->query('date_to', ''),
            'overdue' => $isOverdue,
        ];

        return $exportService->export($filters, 'bd-tasks');
    }
}
