<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Services\DesignTaskExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskExportController extends Controller
{
    public function export(Request $request, DesignTaskExportService $exportService): StreamedResponse
    {
        abort_unless($request->user()?->role === 'designer_head', 403);

        $priority = (string) $request->query('priority', '');
        $isOverdue = $priority === 'overdue';

        $filters = [
            'search' => (string) $request->query('search', ''),
            'vertical' => (string) $request->query('vertical', ''),
            'priority' => $isOverdue ? '' : $priority,
            'designerId' => (string) $request->query('designer_id', ''),
            'bdId' => (string) $request->query('bd_id', ''),
            'period' => (string) $request->query('period', 'current_month'),
            'dateFrom' => (string) $request->query('date_from', ''),
            'dateTo' => (string) $request->query('date_to', ''),
            'overdue' => $isOverdue,
        ];

        return $exportService->export($filters, 'designer-head-tasks');
    }
}
