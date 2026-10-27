{{-- Admin → Task Monitoring: lazy-loaded tabs (TaskActivityController). All values are Blade-escaped. --}}
@php
    $appTz = config('app.timezone');
    $statusLabel = fn ($s) => $s === null || $s === '' ? '—' : ($statuses[$s] ?? \Illuminate\Support\Str::headline((string) $s));
    $sourceLabels = [
        'task_created' => 'Task created', 'task_assigned' => 'Task assigned', 'draft_created' => 'Assigned from draft',
        'designer_task_created' => 'Created by designer', 'designer' => 'Status moved by designer',
        'task_updation' => 'Progress submitted', 'rework_upload' => 'Rework submitted',
        'progress_completed' => 'Progress reached 100%', 'rework_completed' => 'Rework completed', 'rework_reconciled' => 'Rework reconciled',
        'bd_rework' => 'Sent to rework by BD', 'bd_approval' => 'Approved by BD', 'bd_action' => 'BD action',
        'bd_edit_designer_changed' => 'Designer changed (BD edit)', 'bd_edit_progress_reconciled' => 'Progress reconciled (BD edit)',
        'admin_edit' => 'Admin edit', 'request_auto_rejected' => 'Request auto-rejected', 'backward_approval_applied' => 'Backward move approved',
    ];
@endphp
<style>
    .tm-act{border:1px solid var(--line,#e6e8ec);border-radius:14px;background:#fff;margin-bottom:14px;overflow:hidden}
    .tm-act-tabs{display:flex;gap:4px;padding:10px 12px 0;border-bottom:1px solid #eef0f3;flex-wrap:wrap}
    .tm-act-tab{border:0;background:none;padding:9px 12px;font-size:12px;font-weight:850;color:#667085;border-bottom:2px solid transparent;cursor:pointer}
    .tm-act-tab.is-active{color:#101828;border-bottom-color:#e30613}
    .tm-act-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:12px 14px}
    .tm-act-bar .premium-input,.tm-act-bar .premium-select{max-width:190px}
    .tm-act-sum{display:flex;gap:8px;flex-wrap:wrap;padding:0 14px 12px}
    .tm-act-chip{padding:6px 10px;border:1px solid #e6e8ec;border-radius:10px;font-size:11px;color:#475467;background:#fafbfc}
    .tm-act-chip strong{color:#101828;font-size:13px;margin-left:3px}
    .tm-act .table-wrap{border-top:1px solid #eef0f3}
    .tm-act td{font-size:11.5px;vertical-align:top}
    .tm-act-detail{max-width:360px;white-space:pre-line;word-break:break-word;color:#344054}
    .tm-act-note{padding:10px 14px;font-size:10.5px;color:#98a2b3;border-top:1px solid #f2f4f7}
    .tm-act .badge.rework{background:#fff0c2;color:#7a5200}
</style>

<div class="tm-act-tabs" role="tablist">
    <button type="button" class="tm-act-tab {{ $tab === 'progress' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'progress' ? 'true' : 'false' }}" data-tm-tab="progress">{{ $isToday ? "Today's" : $displayDate }} Submitted Progress Details</button>
    <button type="button" class="tm-act-tab {{ $tab === 'logs' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'logs' ? 'true' : 'false' }}" data-tm-tab="logs">Designer Activity Logs</button>
</div>

<div class="tm-act-bar">
    <input class="premium-input" type="date" data-tm-filter="date" value="{{ $date }}" max="{{ now($tz)->toDateString() }}" aria-label="Date (IST)">
    <input class="premium-input" type="search" data-tm-filter="task" value="{{ $state['task'] ?? '' }}" placeholder="Task ID or name" aria-label="Task">
    @if($tab === 'logs')
        <select class="premium-select" data-tm-filter="type" aria-label="Activity type">
            <option value="">All activity</option>
            @foreach($activityTypes as $key => $label)
                <option value="{{ $key }}" @selected(($state['type'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    @endif
    <span class="muted" style="font-size:11px">{{ $displayDate }}, 00:00 – 23:59 IST · BD / Designer filters above also apply</span>
</div>

@if($tab === 'progress')
    <div class="tm-act-sum">
        <span class="tm-act-chip">Submissions<strong>{{ $progressSummary['submissions'] }}</strong></span>
        <span class="tm-act-chip">Designers<strong>{{ $progressSummary['designers'] }}</strong></span>
        <span class="tm-act-chip">Creatives submitted (progress)<strong>{{ $progressSummary['progress_creatives'] }}</strong></span>
        <span class="tm-act-chip">Rework creatives resubmitted<strong>{{ $progressSummary['rework_creatives'] }}</strong></span>
    </div>
    <div class="table-wrap">
        <table class="premium-table">
            <thead>
                <tr>
                    <th>Submitted At (IST)</th><th>Designer</th><th>BD</th><th>Task</th><th>Type</th>
                    <th>Creatives Submitted</th><th>After Submission</th><th>Current Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($progress as $record)
                    @php $task = $record->task; $isRework = $record->update_type === 'rework'; @endphp
                    <tr>
                        <td style="white-space:nowrap">{{ $record->submitted_at?->timezone($tz)->format('d M Y, h:i A') }}</td>
                        <td>{{ $record->designer?->name ?? '—' }}</td>
                        <td>{{ $task?->assigner?->name ?? '—' }}</td>
                        <td>
                            @if($task && ! $task->trashed())
                                <a class="file-link" href="{{ route('admin.tasks.show', $task) }}"><strong>{{ $task->task_id }}</strong></a>
                            @else
                                <strong>{{ $task?->task_id ?? '—' }}</strong> @if($task?->trashed())<span class="muted">(deleted)</span>@endif
                            @endif
                            <div>{{ $task?->task_name }}</div>
                        </td>
                        <td><span class="badge {{ $isRework ? 'rework' : 'badge-dark' }}">{{ $isRework ? 'Rework #'.($record->rework_count_snapshot ?? '?') : 'Progress' }}</span></td>
                        <td><strong style="font-size:13px">{{ $record->completed_count }}</strong></td>
                        <td style="white-space:nowrap">
                            @if($isRework)
                                <span class="muted">Rework resubmission</span>
                            @else
                                {{ $record->cumulative_completed }} / {{ $record->total_creatives_snapshot }} done
                            @endif
                        </td>
                        <td><span class="badge badge-dark">{{ $statusLabel($task?->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No progress was submitted on {{ $displayDate }} for the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap" data-tm-pagination>{{ $progress->links() }}</div>
    <div class="tm-act-note">Each row is one successfully saved Task Updation / Rework submission; the count is what was submitted in that submission, not the task total. Failed uploads, drafts and comments are not submissions. Video submissions are not a separate type in this system.</div>
@else
    <div class="table-wrap">
        <table class="premium-table">
            <thead>
                <tr>
                    <th>Date &amp; Time (IST)</th><th>Task</th><th>Designer</th><th>BD</th><th>Activity</th>
                    <th>Previous → New</th><th>Details</th><th>Performed By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $task = $logTasks->get($log->design_task_id);
                        $actor = $logUsers->get($log->actor_id);
                        [$requestType, $decider] = array_pad(explode(':', (string) $log->ref, 2), 2, null);
                        $activity = match ($log->kind) {
                            'progress', 'status' => $sourceLabels[$log->ref] ?? \Illuminate\Support\Str::headline((string) $log->ref),
                            'comment' => $log->ref && $log->ref !== 'comment' ? 'Comment ('.\Illuminate\Support\Str::headline($log->ref).')' : 'Comment',
                            'edit' => 'Edited: '.$log->ref,
                            'request' => \Illuminate\Support\Str::headline((string) $requestType).' request'.($decider ? ' — '.\Illuminate\Support\Str::headline($decider).' decision' : ''),
                            'printing_mail' => 'Printing file mail sent',
                            default => \Illuminate\Support\Str::headline((string) $log->kind),
                        };
                        $change = match ($log->kind) {
                            'progress', 'status' => $log->prev_value === $log->new_value ? $statusLabel($log->new_value) : $statusLabel($log->prev_value).' → '.$statusLabel($log->new_value),
                            'edit' => ($log->prev_value ?? '—').' → '.($log->new_value ?? '—'),
                            'request' => \Illuminate\Support\Str::headline((string) $log->new_value),
                            'comment' => 'At: '.$statusLabel($log->prev_value),
                            default => '—',
                        };
                    @endphp
                    <tr>
                        <td style="white-space:nowrap">{{ \Carbon\CarbonImmutable::parse($log->occurred_at, $appTz)->setTimezone($tz)->format('d M Y, h:i A') }}</td>
                        <td>
                            @if($task && ! $task->trashed())
                                <a class="file-link" href="{{ route('admin.tasks.show', $task) }}"><strong>{{ $task->task_id }}</strong></a>
                            @else
                                <strong>{{ $task?->task_id ?? '—' }}</strong> @if($task?->trashed())<span class="muted">(deleted)</span>@endif
                            @endif
                            <div>{{ $task?->task_name }}</div>
                        </td>
                        <td>{{ $logUsers->get($task?->designer_id)?->name ?? '—' }}</td>
                        <td>{{ $logUsers->get($task?->assigned_by)?->name ?? '—' }}</td>
                        <td><span class="badge {{ $log->kind === 'progress' ? 'badge-success' : 'badge-dark' }}">{{ $activityTypes[$log->kind] ?? $log->kind }}</span><div style="margin-top:3px">{{ $activity }}</div></td>
                        <td class="tm-act-detail">{{ \Illuminate\Support\Str::limit($change, 200) }}</td>
                        <td class="tm-act-detail">{{ \Illuminate\Support\Str::limit((string) $log->detail, 300) ?: '—' }}</td>
                        <td>{{ $actor?->name ?? '—' }}@if($actor)<div class="muted" style="font-size:10px">{{ \Illuminate\Support\Str::headline((string) $actor->role) }}</div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No recorded activity on {{ $displayDate }} for the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap" data-tm-pagination>{{ $logs->links() }}</div>
    <div class="tm-act-note">Shows only events the system records: status changes &amp; workflow actions (incl. progress / rework submissions), comments, BD/Admin field edits, decline/split/swap requests and decisions, and printing-file mails. Activity that was never recorded (e.g. file views, logins) cannot appear here. Designer / BD columns show the task's current assignment.</div>
@endif
