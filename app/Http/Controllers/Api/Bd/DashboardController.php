<?php

namespace App\Http\Controllers\Api\Bd;

use App\Http\Controllers\Bd\DashboardController as WebDashboardController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * BD dashboard for the mobile app. Every figure comes from the web controller's analytics() — the same
 * code the web dashboard renders — so the two can never disagree.
 */
class DashboardController extends Controller
{
    public function index(Request $request, WebDashboardController $web): JsonResponse
    {
        $user = $request->user();
        $d = $web->analytics($request);

        return response()->json([
            'user' => [
                'name' => $user->name,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'filters' => [
                'designer' => $d['selectedDesigner'],
                'designer_name' => $d['selectedDesignerName'],
                'month' => $d['selectedMonth'],
                'month_label' => $d['selectedMonthLabel'],
                'designers' => $d['designers']->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'months' => $d['months'],
            ],
            'stats' => $d['stats'],
            'bar' => $d['bar'],
            'trend' => [
                'context' => $d['trendContext'],
                'cards' => $d['trendCards'],
                'line' => $d['line'],
            ],
            'workload' => collect($d['workload'])->map(fn (array $w) => [
                'id' => $w['designer']->id,
                'name' => $w['designer']->name,
                'assigned' => $w['assigned'],
                'pending' => $w['pending'],
                'ready_to_start' => $w['ready_to_start'],
                'in_progress' => $w['in_progress'],
                'waiting' => $w['waiting'],
                'completed' => $w['completed'],
                'overdue' => $w['overdue'],
                'rework_count' => $w['rework_count'],
                'rework_creatives' => $w['rework_creatives'],
                'split' => $w['split'],
                'swap' => $w['swap'],
                'decline' => $w['decline'],
                'rating' => $w['rating'] === null ? null : (float) $w['rating'],
            ])->values(),
            'recent_tasks' => collect($d['taskRows'])->take(12)->map(fn (array $row) => $this->row($row))->values(),
            'overdue' => collect($d['overdue'])->map(fn (array $o) => [
                'id' => $o['task']->id,
                'task_id' => $o['task']->task_id,
                'name' => $o['task']->display_task_name ?? $o['task']->task_name,
                'designer' => $o['task']->designer?->name,
                'status' => $o['task']->status,
                'due_at' => $o['task']->due_at?->toIso8601String(),
                'days' => $o['days'],
                'done' => $o['done'],
                'total' => $o['total'],
                'percentage' => $o['percentage'],
            ])->values(),
            'rework' => collect($d['reworkRows'])->take(30)->map(fn (array $r) => [
                'id' => $r['task']->id,
                'task_id' => $r['task']->task_id,
                'name' => $r['task']->display_task_name ?? $r['task']->task_name,
                'designer' => $r['task']->designer?->name,
                'status' => $r['task']->status,
                'rework_number' => $r['rework_number'],
                'rework_assigned_at' => optional($r['rework_assigned_at'])->toIso8601String(),
                'rework_creatives' => $r['rework_creatives'],
                'rework_spent' => $r['rework_spent_text'],
            ])->values(),
            'ratings' => collect($d['completedRatings']['rows'])->map(fn ($r) => [
                'id' => $r->id,
                'task_db_id' => $r->task?->id,
                'task_id' => $r->task?->task_id,
                'task_name' => $r->task?->display_task_name ?? $r->task?->task_name,
                'designer' => $r->task?->designer?->name,
                'reviewer' => $r->submitter?->name,
                'rating' => $r->overall_rating === null ? null : (float) $r->overall_rating,
                'comment' => $r->comment,
                'reviewed_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'completions' => collect($d['completions'])->map(fn (array $c) => [
                'id' => $c['task']->id,
                'task_id' => $c['task']->task_id,
                'name' => $c['task']->display_task_name ?? $c['task']->task_name,
                'designer' => $c['task']->designer?->name,
                'completed_at' => $c['completed_at']->toIso8601String(),
                'duration' => $c['duration_text'],
                'rating' => $c['rating'] === null ? null : (float) $c['rating'],
                'rework_count' => $c['rework_count'],
            ])->values(),
        ]);
    }

    /** "BD Tickets" for every task in the period, narrowed by the same text search the web fragment supports. */
    public function tasks(Request $request, WebDashboardController $web): JsonResponse
    {
        $d = $web->analytics($request);

        return response()->json([
            'data' => collect($d['taskRows'])->map(fn (array $row) => $this->row($row))->values(),
        ]);
    }

    /** @param array<string, mixed> $row one entry of the web analytics' task rows */
    private function row(array $row): array
    {
        $task = $row['task'];
        $completed = $row['completed_at'];

        return [
            'id' => $task->id,
            'task_id' => $task->task_id,
            'project_number' => $task->zoho_project_number,
            'name' => $task->display_task_name ?? $task->task_name,
            'party' => $task->party_name,
            'vertical' => $task->vertical,
            'task_nature' => $task->task_nature,
            'designer' => $task->designer?->name,
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
            'completed_at' => $completed instanceof Carbon ? $completed->toIso8601String() : ($completed ? Carbon::parse($completed)->toIso8601String() : null),
            'overdue' => (bool) $row['overdue'],
            'completion' => $row['completion'],
            'rating' => $row['rating'] === null ? null : (float) $row['rating'],
        ];
    }
}
