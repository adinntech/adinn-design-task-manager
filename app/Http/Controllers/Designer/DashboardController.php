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

        return view('designer.dashboard', app(\App\Services\DesignerDashboardService::class)->build($designer));
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

        return view('designer.dashboard-task-rows', [
            'taskRows' => $this->buildTaskRows((int) $request->user()->id, $search),
            'search' => $search,
        ]);
    }

    private function buildTaskRows(int $designerId, string $search = ''): Collection
    {
        return app(\App\Services\DesignerDashboardService::class)->taskRows($designerId, $search);
    }
}
