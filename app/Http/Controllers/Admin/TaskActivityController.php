<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\User;
use App\Services\DesignTaskStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Admin → Task Monitoring, below the BD × Designer breakdown: two lazy-loaded,
 * read-only tabs for one IST day.
 *  - "progress": persisted Task Updation / Rework submissions
 *    (design_task_eod_records) with the count submitted in each one;
 *  - "logs": real audit rows only — status history (which already records
 *    every progress/rework submission), comments, BD/Admin field edits,
 *    decline/split/swap requests and their decisions, printing-file mails.
 * Nothing is reconstructed from current task state.
 */
class TaskActivityController extends Controller
{
    public const ACTIVITY_TYPES = [
        'progress' => 'Progress submission',
        'status' => 'Status / workflow',
        'comment' => 'Comment',
        'edit' => 'Task edit',
        'request' => 'Request',
        'printing_mail' => 'Printing file mail',
    ];

    /** Status-history sources that are progress submissions, not status moves. */
    private const PROGRESS_SOURCES = ['task_updation', 'rework_upload'];

    public function index(Request $request): View
    {
        // A fragment endpoint: never redirect on bad input (fetch would follow the
        // redirect and inject a whole page) — answer 422 so the tab shows its error state.
        $validator = Validator::make($request->query(), [
            'tab' => ['nullable', 'in:progress,logs'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'bd_id' => ['nullable', 'integer'],
            'designer_id' => ['nullable', 'integer'],
            'task' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', 'in:'.implode(',', array_keys(self::ACTIVITY_TYPES))],
        ]);
        abort_if($validator->fails(), 422, 'Invalid filters.');
        $filters = $validator->validated();

        $tz = (string) config('daily-report.timezone', 'Asia/Kolkata');
        $date = $filters['date'] ?? CarbonImmutable::now($tz)->toDateString();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $tz);
        // Timestamps are stored in the app timezone, so compare there.
        $appTz = (string) config('app.timezone');
        $window = [
            $day->startOfDay()->setTimezone($appTz)->format('Y-m-d H:i:s'),
            $day->endOfDay()->setTimezone($appTz)->format('Y-m-d H:i:s'),
        ];

        $tab = $filters['tab'] ?? 'progress';
        $state = array_filter([
            'tab' => $tab,
            'date' => $date,
            'bd_id' => $filters['bd_id'] ?? null,
            'designer_id' => $filters['designer_id'] ?? null,
            'task' => isset($filters['task']) ? trim($filters['task']) : null,
            'type' => $filters['type'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $data = $tab === 'logs'
            ? $this->logs($state, $window)
            : $this->progress($state, $window);

        return view('admin.tasks.partials.activity-tabs', $data + [
            'tab' => $tab,
            'state' => $state,
            'date' => $date,
            'displayDate' => $day->format('d M Y'),
            'isToday' => $date === CarbonImmutable::now($tz)->toDateString(),
            'activityTypes' => self::ACTIVITY_TYPES,
            'statuses' => DesignTaskStatusService::STATUSES,
            'tz' => $tz,
        ]);
    }

    /** Tab 1 — one row per persisted submission; counts are the submitted amount, never the task total. */
    private function progress(array $state, array $window): array
    {
        $base = DesignTaskEodRecord::query()
            ->whereIn('update_type', ['progress', 'rework'])
            ->whereBetween('submitted_at', $window)
            ->when($state['designer_id'] ?? null, fn ($q, $id) => $q->where('designer_id', $id))
            ->when(isset($state['bd_id']) || isset($state['task']), fn ($q) => $q->whereIn(
                'design_task_id',
                $this->taskScope($state, includeDesigner: false)
            ));

        $summary = (clone $base)
            ->selectRaw('update_type, COUNT(*) AS submissions, COALESCE(SUM(completed_count), 0) AS creatives, COUNT(DISTINCT designer_id) AS designers')
            ->groupBy('update_type')
            ->get()
            ->keyBy('update_type');

        $rows = (clone $base)
            ->with([
                'designer:id,name',
                'task' => fn ($q) => $q->withTrashed()->select('id', 'task_id', 'task_name', 'status', 'assigned_by', 'deleted_at'),
                'task.assigner:id,name',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(25, ['id', 'design_task_id', 'designer_id', 'update_type', 'completed_count', 'total_creatives_snapshot', 'cumulative_completed', 'rework_count_snapshot', 'submitted_at'], 'progress_page')
            ->withPath(route('admin.tasks.activity'))
            ->appends($state);

        return [
            'progress' => $rows,
            'progressSummary' => [
                'submissions' => (int) $summary->sum('submissions'),
                'designers' => (int) (clone $base)->distinct()->count('designer_id'),
                'progress_creatives' => (int) ($summary->get('progress')->creatives ?? 0),
                'rework_creatives' => (int) ($summary->get('rework')->creatives ?? 0),
                'rework_submissions' => (int) ($summary->get('rework')->submissions ?? 0),
            ],
        ];
    }

    /** Tab 2 — UNION of the existing audit tables, newest first, paginated in SQL. */
    private function logs(array $state, array $window): array
    {
        $scoped = isset($state['bd_id']) || isset($state['designer_id']) || isset($state['task']);
        $scope = fn (Builder $q, string $column = 'design_task_id') => $scoped
            ? $q->whereIn($column, $this->taskScope($state))
            : $q;
        $null = DB::raw('CAST(NULL AS CHAR)');

        $parts = [
            $scope(DB::table('design_task_status_histories')
                ->whereBetween('created_at', $window)
                ->select([
                    DB::raw('created_at AS occurred_at'),
                    DB::raw("CASE WHEN change_source IN ('".implode("','", self::PROGRESS_SOURCES)."') THEN 'progress' ELSE 'status' END AS kind"),
                    'design_task_id', DB::raw('changed_by AS actor_id'),
                    DB::raw('from_status AS prev_value'), DB::raw('to_status AS new_value'),
                    DB::raw('note AS detail'), DB::raw('change_source AS ref'),
                    DB::raw("CONCAT('sh-', id) AS uid"),
                ])),
            $scope(DB::table('design_task_comments')
                ->whereBetween('created_at', $window)
                ->select([
                    DB::raw('created_at AS occurred_at'), DB::raw("'comment' AS kind"),
                    'design_task_id', DB::raw('user_id AS actor_id'),
                    DB::raw('status_at_comment AS prev_value'), $null,
                    DB::raw('comment AS detail'), DB::raw('context AS ref'),
                    DB::raw("CONCAT('cm-', id) AS uid"),
                ])),
            $scope(DB::table('design_task_edit_histories')
                ->whereBetween('created_at', $window)
                ->select([
                    DB::raw('created_at AS occurred_at'), DB::raw("'edit' AS kind"),
                    'design_task_id', DB::raw('edited_by AS actor_id'),
                    DB::raw('old_value AS prev_value'), DB::raw('new_value AS new_value'),
                    $null, DB::raw('field_name AS ref'),
                    DB::raw("CONCAT('ed-', id) AS uid"),
                ])),
            $scope(DB::table('design_task_requests')
                ->whereBetween('created_at', $window)
                ->select([
                    DB::raw('created_at AS occurred_at'), DB::raw("'request' AS kind"),
                    'design_task_id', DB::raw('requested_by AS actor_id'),
                    $null, DB::raw("'requested' AS new_value"),
                    DB::raw('reason AS detail'), DB::raw('request_type AS ref'),
                    DB::raw("CONCAT('rq-', id) AS uid"),
                ])),
            $scope(DB::table('design_task_requests')
                ->whereNotNull('designer_head_action_at')
                ->whereBetween('designer_head_action_at', $window)
                ->select([
                    DB::raw('designer_head_action_at AS occurred_at'), DB::raw("'request' AS kind"),
                    'design_task_id', DB::raw('designer_head_action_by AS actor_id'),
                    $null, DB::raw('designer_head_status AS new_value'),
                    DB::raw('decision_reason AS detail'), DB::raw("CONCAT(request_type, ':designer_head') AS ref"),
                    DB::raw("CONCAT('rh-', id) AS uid"),
                ])),
            $scope(DB::table('design_task_requests')
                ->whereNotNull('admin_action_at')
                ->whereBetween('admin_action_at', $window)
                ->select([
                    DB::raw('admin_action_at AS occurred_at'), DB::raw("'request' AS kind"),
                    'design_task_id', DB::raw('admin_action_by AS actor_id'),
                    $null, DB::raw('admin_status AS new_value'),
                    DB::raw('decision_reason AS detail'), DB::raw("CONCAT(request_type, ':admin') AS ref"),
                    DB::raw("CONCAT('ra-', id) AS uid"),
                ])),
            $scope(DB::table('design_task_printing_file_mails')
                ->whereBetween('sent_at', $window)
                ->select([
                    DB::raw('sent_at AS occurred_at'), DB::raw("'printing_mail' AS kind"),
                    'design_task_id', DB::raw('sender_id AS actor_id'),
                    $null, $null,
                    DB::raw('subject AS detail'), $null,
                    DB::raw("CONCAT('pm-', id) AS uid"),
                ])),
        ];

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        $page = DB::query()
            ->fromSub($union, 'activity')
            ->when($state['type'] ?? null, fn ($q, $type) => $q->where('kind', $type))
            ->orderByDesc('occurred_at')
            ->orderByDesc('uid')
            ->paginate(30, ['*'], 'logs_page')
            ->withPath(route('admin.tasks.activity'))
            ->appends($state);

        $items = collect($page->items());
        $tasks = DesignTask::withTrashed()
            ->whereIn('id', $items->pluck('design_task_id')->unique())
            ->get(['id', 'task_id', 'task_name', 'status', 'designer_id', 'assigned_by', 'deleted_at'])
            ->keyBy('id');
        $users = User::query()
            ->whereIn('id', $items->pluck('actor_id')
                ->merge($tasks->pluck('designer_id'))
                ->merge($tasks->pluck('assigned_by'))
                ->filter()->unique())
            ->get(['id', 'name', 'role'])
            ->keyBy('id');

        return ['logs' => $page, 'logTasks' => $tasks, 'logUsers' => $users];
    }

    /** Task ids matching the BD / Designer / Task-ID filters (deleted tasks included, drafts excluded). */
    private function taskScope(array $state, bool $includeDesigner = true)
    {
        return DesignTask::withTrashed()
            ->where('status', '!=', 'draft')
            ->when($state['bd_id'] ?? null, fn ($q, $id) => $q->where('assigned_by', $id))
            ->when($includeDesigner ? ($state['designer_id'] ?? null) : null, fn ($q, $id) => $q->where('designer_id', $id))
            ->when($state['task'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('task_id', 'like', '%'.$term.'%')
                ->orWhere('task_name', 'like', '%'.$term.'%')))
            ->select('id');
    }
}
