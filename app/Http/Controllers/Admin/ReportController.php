<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
use App\Services\DesignTaskExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-wide task report — reuses the exact same DesignTaskExportService
 * (and its underlying DesignerHeadTaskBoardService period/cross-month logic)
 * already shared by Designer/Designer Head/BD exports, just with BD and
 * Designer left as open filters (Admin has no forced scope).
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $bds = User::query()->where('role', 'bd')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $designers = User::query()->where('role', 'designer')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.reports.index', compact('bds', 'designers'));
    }

    public function export(Request $request, DesignTaskExportService $exportService): StreamedResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $filters = $this->filtersFromRequest($request);
        $bdName = $filters['bdId'] !== '' ? User::find($filters['bdId'])?->name : null;
        $designerName = $filters['designerId'] !== '' ? User::find($filters['designerId'])?->name : null;
        $prefix = $exportService->filenamePrefix($bdName, $designerName, 'admin-overall-report');

        return $exportService->export($filters, $prefix);
    }

    /**
     * Lightweight "how many tasks match" count for the auto-load loading
     * state — reuses the exact same board-building service the export uses
     * (no separate query logic), just reads its count instead of building
     * the full Excel row/style pipeline.
     */
    public function summary(Request $request, DesignerHeadTaskBoardService $boardService): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $board = $boardService->build($this->filtersFromRequest($request));
        $count = $board['tasks']->count() + $board['swapShadowTasks']->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Split view data for a selected BD: per-status task counts plus the exact
     * Tasks-sheet rows (and their highlight flags) the export would write.
     */
    public function preview(Request $request, DesignTaskExportService $exportService): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $report = $exportService->buildReport($this->filtersFromRequest($request));

        $counts = $report['tasks']->countBy('status');
        $statusCounts = collect($report['statuses'])
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) $counts->get($key, 0)])
            ->values();
        foreach ($counts as $key => $count) {
            if (! array_key_exists($key, $report['statuses'])) {
                $statusCounts->push(['key' => $key, 'label' => ucwords(str_replace('_', ' ', (string) $key)), 'count' => $count]);
            }
        }

        $rowFlags = [];
        foreach (array_keys($report['rows']) as $index) {
            $rowNumber = $index + 1;
            $rowFlags[] = [
                'carryForward' => in_array($rowNumber, $report['carryForwardRowNumbers'], true),
                'overdue' => in_array($rowNumber, $report['activeOverdueRowNumbers'], true),
                'completedLate' => in_array($rowNumber, $report['overdueCompletedRowNumbers'], true),
            ];
        }

        return response()->json([
            'statusCounts' => $statusCounts->values(),
            'total' => count($report['rows']),
            'header' => $exportService->header(),
            'rows' => $report['rows'],
            'rowFlags' => $rowFlags,
        ]);
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'search' => '',
            'vertical' => '',
            'priority' => '',
            'designerId' => (string) $request->query('designer_id', ''),
            'bdId' => (string) $request->query('bd_id', ''),
            'projectNumber' => '',
            'period' => (string) $request->query('period', 'current_month'),
            'dateFrom' => (string) $request->query('date_from', ''),
            'dateTo' => (string) $request->query('date_to', ''),
            'overdue' => false,
        ];
    }
}
