<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer', 403);

        $designer = $request->user();

        [$month, $from, $to] = $this->periodInput($request);

        return view('designer.dashboard', app(\App\Services\DesignerDashboardService::class)->build($designer, $month, $from, $to));
    }

    /**
     * Global dashboard search — AJAX-only, returns just the "Your Task Details"
     * rows. Deliberately a separate lightweight query rather than reusing
     * index()'s big pipeline, so the normal (non-search) page load never pays
     * for extra queries and index() stays untouched.
     */
    public function fragment(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer', 403);

        $search = trim((string) $request->query('search', ''));

        [$month, $from, $to] = $this->periodInput($request);

        return view('designer.dashboard-task-rows', [
            'taskRows' => $this->buildTaskRows((int) $request->user()->id, $search, $month, $from, $to),
            'search' => $search,
        ]);
    }

    private function buildTaskRows(int $designerId, string $search = '', ?string $month = null, ?string $from = null, ?string $to = null): Collection
    {
        return app(\App\Services\DesignerDashboardService::class)->taskRows($designerId, $search, $month, $from, $to);
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} month ("YYYY-MM" or "overall"), range from, range to */
    private function periodInput(Request $request): array
    {
        $pick = fn (string $key) => is_string($request->query($key)) ? $request->query($key) : null;

        return [$pick('month'), $pick('from'), $pick('to')];
    }
}
