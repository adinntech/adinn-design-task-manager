<?php

namespace App\Http\Controllers\Api\Designer;

use App\Http\Controllers\Controller;
use App\Models\DesignTaskRequest;
use App\Services\DesignerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Designer dashboard for the mobile app. All numbers come from DesignerDashboardService — the same
 * code the web dashboard renders — so the two can never disagree.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DesignerDashboardService $dashboard): JsonResponse
    {
        $user = $request->user();
        $d = $dashboard->build($user, $this->query($request, 'month'), $this->query($request, 'from'), $this->query($request, 'to'));

        // The web query leaves these lazy (one extra query per row); eager-load for the 100 rows we return.
        $d['myRequests']->each->loadMissing(['requester:id,name', 'designerHeadActor:id,name', 'adminActor:id,name']);

        return response()->json([
            'user' => [
                'name' => $user->name,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'period' => $d['period'],
            'stats' => $d['stats'],
            'completion_rate' => $d['completionRate'],
            'overall_rating' => $d['overallRating'],
            'overall_rework' => $d['overallRework'],
            'request_counts' => $d['requestTypeCounts'],
            'recent_tasks' => $d['taskRows']->take(12)->map(fn (array $row) => $this->row($row))->values(),
            'trend' => [
                'context' => $d['trendContext'],
                'cards' => $d['trendCards'],
                'line' => $d['line'],
            ],
            'reviews' => $d['reviewCards']->map(fn (array $r) => [
                'rating' => $r['rating'],
                'comment' => $r['comment'],
                'task_db_id' => $r['task']?->id,
                'task_id' => $r['task_id'],
                'task_name' => $r['task_name'],
                'reviewer' => $r['reviewer'],
                'reviewed_at' => $r['reviewed_at']?->toIso8601String(),
                'completed_at' => $this->iso($r['completed_at']),
            ])->values(),
            'my_requests' => $d['myRequests']->map(fn (DesignTaskRequest $r) => $this->request($r))->values(),
        ]);
    }

    /** "Your Task Details" for every task, narrowed by the same text search the web fragment supports. */
    public function tasks(Request $request, DesignerDashboardService $dashboard): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        return response()->json([
            'data' => $dashboard->taskRows((int) $request->user()->id, $search, $this->query($request, 'month'), $this->query($request, 'from'), $this->query($request, 'to'))->map(fn (array $row) => $this->row($row))->values(),
        ]);
    }

    private function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $row one entry of DesignerDashboardService's task rows */
    private function row(array $row): array
    {
        $task = $row['task'];

        return [
            'id' => $task->id,
            'task_id' => $task->task_id,
            'project_number' => $task->zoho_project_number,
            'name' => $task->display_task_name ?? $task->task_name,
            'party' => $task->party_name,
            'vertical' => $task->vertical,
            'task_nature' => $task->task_nature,
            'assigned_by' => $task->assigner?->name,
            'status' => $task->status,
            'priority' => $task->priority,
            'assigned_at' => $task->assigned_at?->toIso8601String(),
            'due_at' => $task->due_at?->toIso8601String(),
            'total_creatives' => (int) $task->total_creatives,
            'done' => $row['done'],
            'remaining' => $row['remaining'],
            'percentage' => $row['percentage'],
            'rework_count' => $row['rework_count'],
            'rework_creatives' => $row['rework_creatives'],
            'completed_at' => $this->iso($row['completed_at']),
            'overdue' => (bool) $row['overdue'],
            'completion' => $row['completion'],
            'rating' => $row['rating'] === null ? null : (float) $row['rating'],
        ];
    }

    private function request(DesignTaskRequest $r): array
    {
        $pending = in_array($r->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true);

        // Same rule as the web card: an approved swap/split is now with the approved designer.
        $handler = ($r->overall_status === 'approved' && in_array($r->request_type, ['swap', 'split'], true))
            ? ($r->approvedDesigner?->name ?? null)
            : ($r->task?->designer?->name ?? null);

        return [
            'id' => $r->id,
            'type' => $r->request_type,
            'status' => $pending ? 'pending' : $r->overall_status,
            'task_db_id' => $r->task?->id,
            'task_id' => $r->task?->task_id,
            'task_name' => $r->task?->display_task_name ?? $r->task?->task_name,
            'requested_by' => $r->requester?->name,
            'requested_at' => $r->created_at?->toIso8601String(),
            'responded_by' => $r->decided_by?->name,
            'responded_at' => $r->responded_at?->toIso8601String(),
            'current_handler' => $handler,
        ];
    }

    private function iso(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toIso8601String() : ($value ? Carbon::parse($value)->toIso8601String() : null);
    }
}
