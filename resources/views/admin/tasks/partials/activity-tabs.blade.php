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
    .tm-export{padding:16px 14px}
    .tm-act-bar.tm-export .premium-input,.tm-act-bar.tm-export .premium-select{max-width:none;flex:1 1 170px;min-width:150px}
    .tm-export input[hidden]{display:none}
    .tm-export-btn{flex:0 0 auto;min-width:150px;font-weight:900}
</style>

<div class="tm-act-tabs" role="tablist">
    <button type="button" class="tm-act-tab {{ $tab === 'progress' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'progress' ? 'true' : 'false' }}" data-tm-tab="progress">{{ $isToday ? "Today's" : $displayDate }} Submitted Progress Details</button>
    <button type="button" class="tm-act-tab {{ $tab === 'logs' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'logs' ? 'true' : 'false' }}" data-tm-tab="logs">Designer Activity Logs</button>
    <button type="button" class="tm-act-tab {{ $tab === 'export' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'export' ? 'true' : 'false' }}" data-tm-tab="export">Report Summary</button>
    <button type="button" class="tm-act-tab {{ $tab === 'daily' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'daily' ? 'true' : 'false' }}" data-tm-tab="daily">Daily Report</button>
</div>

@if(! in_array($tab, ['export', 'daily'], true))
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
@endif

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
@elseif($tab === 'export')
    {{-- Same report as BD → Assigned Tasks → Export Report (DesignTaskExportService), across all BDs. --}}
    <form class="tm-act-bar tm-export" method="GET" action="{{ route('admin.reports.export') }}" data-tm-export>
        @if(! empty($state['bd_id']))<input type="hidden" name="bd_id" value="{{ $state['bd_id'] }}">@endif
        <select class="premium-select" name="vertical" aria-label="Vertical">
            <option value="">All Verticals</option>
            @foreach(['outdoor' => 'Outdoor', 'roadshow' => 'RoadShow', 'fixtures' => 'Fixtures', 'signage' => 'Signage', 'pop_offsets' => 'POP and Offsets', 'digital_marketing' => 'Digital Marketing', 'events_activations' => 'Events and Activations'] as $k => $v)
                <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
        <select class="premium-select" name="priority" aria-label="Priority">
            <option value="">All Priorities</option>
            @foreach(['overdue' => 'Overdue', 'urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $k => $v)
                <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
        <select class="premium-select" name="designer_id" aria-label="Designer">
            <option value="">All Designers</option>
            @foreach($exportDesigners as $designerOption)
                <option value="{{ $designerOption->id }}" @selected((string) ($state['designer_id'] ?? '') === (string) $designerOption->id)>{{ $designerOption->name }}</option>
            @endforeach
        </select>
        <input class="premium-input" type="search" name="project_number" placeholder="Zoho Project Number" autocomplete="off" aria-label="Zoho Project Number">
        <select class="premium-select" name="period" aria-label="Period" data-tm-export-period>
            <option value="current_month">Current Month</option>
            <option value="last_month">Last Month</option>
            <option value="custom">Custom Period</option>
        </select>
        <input class="premium-input" type="date" name="date_from" aria-label="From date" data-tm-export-date hidden disabled>
        <input class="premium-input" type="date" name="date_to" aria-label="To date" data-tm-export-date hidden disabled>
        <button type="submit" class="btn btn-secondary tm-export-btn">Export Report</button>
    </form>
    <div class="tm-act-note">
        Downloads the same Excel report as BD → Assigned Tasks → Export Report, for every BD's tasks.
        @if(! empty($state['bd_id'])) The BD selected in the filters above also applies. @endif
    </div>
@elseif($tab === 'daily')
    {{-- Calendar + download; behaviour in admin/tasks/index.blade.php (tmDailyInit), Excel from ProgressExcelController. --}}
    <style>
        .tm-daily{display:flex;gap:28px;flex-wrap:wrap;align-items:flex-start;padding:18px 16px}
        .tm-daily-side{flex:1 1 300px;min-width:260px}
        .tm-xl-presets{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
        .tm-xl-preset{border:1px solid #d0d5dd;background:#fff;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:800;cursor:pointer;color:#344054}
        .tm-xl-preset.is-active{background:#101828;border-color:#101828;color:#fff}
        .tm-xl-cal{flex:0 1 340px;width:100%;max-width:340px;border:1px solid #eef0f3;border-radius:16px;padding:14px 12px 10px;box-shadow:0 8px 24px rgba(16,24,40,.07);background:#fff}
        .tm-xl-cal-head{display:flex;align-items:center;justify-content:space-between;padding:0 4px 10px}
        .tm-xl-cal-title{font-size:14px;font-weight:800;color:#344054;letter-spacing:.01em}
        .tm-xl-cal-nav{width:30px;height:30px;border:0;border-radius:50%;background:none;color:#98a2b3;font-size:20px;line-height:1;cursor:pointer;display:grid;place-items:center}
        .tm-xl-cal-nav:hover:not(:disabled){background:#f2f4f7;color:#344054}
        .tm-xl-cal-nav:disabled{opacity:.3;cursor:default}
        .tm-xl-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);row-gap:4px}
        .tm-xl-cal-dow{text-align:center;font-size:10px;font-weight:700;letter-spacing:.08em;color:#98a2b3;padding:4px 0 8px}
        .tm-xl-cal-cell{display:flex;justify-content:center;align-items:center;height:38px}
        .tm-xl-cal-cell.in-range{background:#fdecee}
        .tm-xl-cal-cell.range-start{background:linear-gradient(to right,transparent 50%,#fdecee 50%)}
        .tm-xl-cal-cell.range-end{background:linear-gradient(to left,transparent 50%,#fdecee 50%)}
        .tm-xl-cal-cell.range-start.range-end{background:none}
        .tm-xl-cal-day{width:36px;height:36px;border:0;border-radius:50%;background:none;font-size:13.5px;font-weight:600;color:#344054;cursor:pointer;font-variant-numeric:tabular-nums}
        .tm-xl-cal-day:hover:not(:disabled):not(.is-selected){background:#f2f4f7}
        .tm-xl-cal-day.is-today:not(.is-selected){box-shadow:inset 0 0 0 1.5px #e30613;color:#e30613;font-weight:800}
        .tm-xl-cal-day.is-selected{background:#e30613;color:#fff;font-weight:800;box-shadow:0 4px 10px rgba(227,6,19,.3)}
        .tm-xl-cal-day:disabled{color:#d0d5dd;cursor:default}
        .tm-xl-cal-day:focus-visible{outline:2px solid #101828;outline-offset:2px}
        .tm-xl-picked{font-size:14px;color:#475467;margin:0 0 10px}
        .tm-xl-picked strong{color:#101828}
        .tm-xl-cols{margin:12px 0 0;padding:10px 12px;background:#f9fafb;border-radius:10px;font-size:11.5px;color:#475467;line-height:1.6}
        .tm-xl-help{font-size:11px;color:#667085;margin:8px 0 0}
        .tm-xl-msg{font-size:12px;font-weight:700;margin-top:10px;padding:8px 10px;border-radius:8px}
        .tm-xl-msg.is-error{background:#fef3f2;color:#b42318}
        .tm-xl-msg.is-ok{background:#ecfdf3;color:#027a48}
        @media(max-width:520px){.tm-xl-cal-day{width:34px;height:34px}}
        /* Live Excel — top-right of the tab; the dot blinks while the Google Sheet is live. */
        .tm-daily{position:relative}
        .tm-live{position:absolute;top:18px;right:16px;z-index:20}
        .tm-live-btn{display:inline-flex;align-items:center;gap:8px;border:1.5px solid #1d6f42;background:#fff;color:#1d6f42;border-radius:999px;padding:7px 14px;font-size:12px;font-weight:900;cursor:pointer}
        .tm-live-btn.is-on{background:#1d6f42;color:#fff}
        .tm-live-dot{width:9px;height:9px;border-radius:50%;background:#e30613;animation:tmLiveBlink 1.1s ease-in-out infinite}
        .tm-live-btn.is-on .tm-live-dot{background:#4ade80;box-shadow:0 0 0 3px rgba(74,222,128,.25)}
        @keyframes tmLiveBlink{0%,100%{opacity:1}50%{opacity:.2}}
        @media (prefers-reduced-motion:reduce){.tm-live-dot{animation:none}}
        .tm-live-panel{position:absolute;right:0;top:calc(100% + 8px);width:360px;max-width:calc(100vw - 32px);background:#fff;border:1px solid #e6e8ec;border-radius:14px;box-shadow:0 16px 40px rgba(16,24,40,.16);padding:16px;font-size:12px;color:#344054}
        .tm-live-panel[hidden]{display:none}
        .tm-live-panel h4{margin:0 0 8px;font-size:13px;font-weight:900;color:#101828}
        .tm-live-panel ol{margin:6px 0 0;padding-left:18px;line-height:1.6}
        .tm-live-panel code{background:#f2f4f7;border-radius:5px;padding:1px 5px;font-size:11px;word-break:break-all}
        .tm-live-email{display:flex;gap:6px;align-items:center;margin:6px 0 12px}
        .tm-live-email code{flex:1;padding:6px 8px}
        .tm-live-panel .premium-input{width:100%;margin:4px 0 10px}
        .tm-live-row{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:12px}
        .tm-live-meta{color:#667085;font-size:11px;margin-top:6px}
        .tm-live-err{background:#fef3f2;color:#b42318;border-radius:8px;padding:7px 9px;margin-top:8px;font-weight:700;font-size:11px}
        @media(min-width:901px){.tm-daily .tm-xl-presets{padding-right:140px}}
        @media(max-width:900px){.tm-live{position:static;width:100%;display:flex;justify-content:flex-end}.tm-live-panel{position:absolute;top:auto;right:16px}}
    </style>
    <div class="tm-daily" data-xl-root>
        @php $liveOn = $live['enabled'] && $live['spreadsheet_id'] && $liveConfigured; @endphp
        <div class="tm-live">
            <button type="button" class="tm-live-btn {{ $liveOn ? 'is-on' : '' }}" data-live-toggle aria-expanded="false" title="{{ $liveOn ? 'Google Sheet is live' : 'Connect a Google Sheet that updates automatically' }}">
                <span class="tm-live-dot" aria-hidden="true"></span> Live Excel
            </button>
            <div class="tm-live-panel" data-live-panel hidden>
                @if(! $liveConfigured)
                    <h4>Set up Live Excel (one time)</h4>
                    <ol>
                        <li>In Google Cloud Console, enable the <strong>Google Sheets API</strong>.</li>
                        <li>Create a <strong>service account</strong> and download its JSON key.</li>
                        <li>Upload the key to the server at <code>{{ str_replace(base_path().DIRECTORY_SEPARATOR, '', config('google-sheets.credentials')) }}</code>.</li>
                        <li>Reload this page, then connect your Google Sheet here.</li>
                    </ol>
                @elseif($liveOn)
                    <h4>Connected — updates daily at 11:59 PM</h4>
                    <div>Connected to <strong>{{ $live['title'] ?: 'Google Sheet' }}</strong>. Daily, Weekly, Monthly and Yearly tabs from the start until now (newest first), plus AI Insights with Period / Designer filters. They update automatically every night at 11:59 PM (IST); click Sync now to update them right away. Click + on a row to see its designers.</div>
                    <div class="tm-live-meta">Last updated: {{ $live['last_synced_at'] ? \Carbon\CarbonImmutable::parse($live['last_synced_at'])->setTimezone(config('daily-report.timezone'))->format('d M Y, h:i:s A') : 'not yet' }}</div>
                    @if($live['last_error'])<div class="tm-live-err">{{ $live['last_error'] }}</div>@endif
                    <div class="tm-live-row">
                        <button type="button" class="btn btn-secondary" data-live-action="disconnect">Turn off</button>
                        <button type="button" class="btn btn-secondary" data-live-action="sync">Sync now</button>
                        <a class="btn btn-primary" href="https://docs.google.com/spreadsheets/d/{{ $live['spreadsheet_id'] }}/edit" target="_blank" rel="noopener">Open Google Sheet</a>
                    </div>
                @else
                    <h4>Connect a Google Sheet</h4>
                    <div>1. Create a Google Sheet and <strong>share it as Editor</strong> with:</div>
                    <div class="tm-live-email"><code data-live-email>{{ $liveEmail ?? '—' }}</code><button type="button" class="btn btn-secondary" data-live-copy>Copy</button></div>
                    <label for="tm-live-sheet">2. Paste the sheet link</label>
                    <input class="premium-input" id="tm-live-sheet" type="url" placeholder="https://docs.google.com/spreadsheets/d/…" value="{{ $live['spreadsheet_id'] ? 'https://docs.google.com/spreadsheets/d/'.$live['spreadsheet_id'].'/edit' : '' }}" data-live-sheet>
                    @if($live['last_error'])<div class="tm-live-err">{{ $live['last_error'] }}</div>@endif
                    <div class="tm-live-row"><button type="button" class="btn btn-primary" data-live-action="connect">Connect</button></div>
                @endif
                <div class="tm-xl-msg" data-live-msg hidden></div>
            </div>
        </div>
        <input type="hidden" id="tm-xl-from">
        <input type="hidden" id="tm-xl-to">
        <div class="tm-xl-cal">
            <div class="tm-xl-cal-head">
                <button type="button" class="tm-xl-cal-nav" data-xl-nav="-1" aria-label="Previous month">‹</button>
                <div class="tm-xl-cal-title" data-xl-cal-title aria-live="polite"></div>
                <button type="button" class="tm-xl-cal-nav" data-xl-nav="1" aria-label="Next month">›</button>
            </div>
            <div class="tm-xl-cal-grid" data-xl-cal-grid></div>
        </div>
        <div class="tm-daily-side">
            <div class="tm-xl-presets">
                <button type="button" class="tm-xl-preset is-active" data-xl-preset="today">Today</button>
                <button type="button" class="tm-xl-preset" data-xl-preset="yesterday">Yesterday</button>
                <button type="button" class="tm-xl-preset" data-xl-preset="week">This week</button>
                <button type="button" class="tm-xl-preset" data-xl-preset="month">This month</button>
                <button type="button" class="tm-xl-preset" data-xl-preset="year">This year</button>
                <button type="button" class="tm-xl-preset" data-xl-preset="custom">Custom Period</button>
            </div>
            <p class="tm-xl-picked" data-xl-picked></p>
            <p class="tm-xl-help" data-xl-layout style="margin:-4px 0 12px"></p>
            <button type="button" class="btn btn-primary" data-xl-download>Download Excel</button>
            <div class="tm-xl-msg" data-xl-msg hidden></div>
            <div class="tm-xl-cols">
                Designer-wise numbers: <strong>Total Tickets</strong>, Carry from Previous Day / Period,
                @foreach(app(\App\Services\DailyWorkReportService::class)->statusColumns() as $label){{ $label }}{{ $loop->last ? '.' : ', ' }}@endforeach
            </div>
            <p class="tm-xl-help" data-xl-filters></p>
            <p class="tm-xl-help">A day covers 12:00 AM – 11:59 PM (IST); today covers till now. Longer periods open collapsed in Excel — click + to expand.</p>
        </div>
    </div>
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
