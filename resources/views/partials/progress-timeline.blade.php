{{--
    Shared Progress Updates tree — included by Designer, Designer Head and BD
    task detail pages so all three roles see the identical design.

    Expects: $timeline (from DesignTaskReportingService::progressTimeline()), $task.

    Hierarchy (true tree):

        Submission (root)
        ├─ Rework #1
        │   ├─ Rework Completion 1
        │   └─ Rework Completion 2
        ├─ Rework #2
        │   └─ Rework Completion 1
        └─ Final BD Approval        (only when the task is completed)

    Every rework cycle is a DIRECT child (sibling branch) of the Submission
    root — never nested under another rework. Each rework holds only its own
    completion submissions. Data is built in DesignTaskReportingService::
    progressTimeline()['flow'] (submission + sibling branches).
--}}
@php
    $ptlProgress = app(\App\Services\DesignTaskProgressService::class);
    $ptlCompleted = $ptlProgress->completed($task);
    $ptlRemaining = $ptlProgress->remaining($task);
    $ptlSubmission = ($timeline['flow']['submission'] ?? null);
    $ptlBranches = ($timeline['flow']['branches'] ?? collect());
    $ptlFinalZipUrl = $task->final_submission_url;
    $ptlIsComplete = $ptlCompleted >= (int) $task->total_creatives;
@endphp
<style>
    .ptl{--ptl-ink:#344054;--ptl-line:#cbd5e1}
    .ptl-zero{color:#15803d!important}
    .ptl-empty{font-size:9px;color:#98a2b3}

    /* ---- tree shell ---- */
    .ptl-tree{position:relative;margin-top:6px}

    /* centered connector from root down to the branch spine */
    .ptl-spine{position:relative;height:28px}
    .ptl-spine::before{content:'';position:absolute;left:50%;top:0;bottom:0;width:2px;background:var(--ptl-line)}
    .ptl-spine::after{content:'';position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:12px;height:12px;border-radius:999px;background:#fff;border:2px solid #7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.15)}

    /* ---- root card (Submission) ---- */
    .ptl-root{position:relative;max-width:640px;margin:0 auto;border:1px solid #dfe3ec;border-radius:14px;background:linear-gradient(180deg,#ffffff,#f4f6fa);padding:16px 18px;box-shadow:0 4px 16px -8px rgba(16,24,40,.18)}
    .ptl-root-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
    .ptl-root-title{display:flex;align-items:center;gap:8px}
    .ptl-root-title .dot{width:9px;height:9px;border-radius:999px;background:#7c3aed;box-shadow:0 0 0 4px rgba(124,58,237,.15)}
    .ptl-root-title strong{font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#1f2937}
    .ptl-root-taskid{font-size:10px;font-weight:700;color:#6b7280}

    .ptl-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px}
    .ptl-cell span{display:block;font-size:8px;text-transform:uppercase;color:#98a2b3;font-weight:800;letter-spacing:.04em}
    .ptl-cell strong{display:block;margin-top:3px;font-size:11px;color:#344054;overflow-wrap:anywhere}
    .ptl-cell.wide{grid-column:1/-1}
    .ptl-cell .ptl-warn{color:#b45309;font-weight:800}
    .ptl-cell .ptl-ok{color:#15803d}
    .ptl-cell .ptl-bad{color:#b42318}

    /* ---- branch row: sibling rework/final cards under the root ---- */
    .ptl-branches{position:relative;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px 16px;align-items:start}
    .ptl-branch{position:relative;min-width:0}
    .ptl-branch::before{content:'';position:absolute;left:50%;top:-28px;height:28px;width:2px;background:var(--ptl-line)}

    .ptl-card{border:1px solid #e7e9ef;border-radius:13px;background:#fff;padding:14px 16px}
    .ptl-card.is-rework{border-color:#f2ce68;background:linear-gradient(180deg,#fffdf7,#fff9e8);box-shadow:inset 4px 0 0 #f5b301}
    .ptl-card.is-final{border-color:#bbe8c8;background:linear-gradient(180deg,#f4fdf7,#ecfaf1);box-shadow:inset 4px 0 0 #16a34a}
    .ptl-card-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
    .ptl-badge{display:inline-flex;align-items:center;min-height:24px;padding:4px 11px;border-radius:999px;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
    .ptl-badge.is-rework{background:#fff0c2;color:#7a5200;border:1px solid #f2cf68}
    .ptl-badge.is-final{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
    .ptl-card-meta{font-size:9px;color:#7c8492}

    /* ---- children under a branch (rework completions) ---- */
    .ptl-children{position:relative;margin-left:16px;padding-left:18px;border-left:2px solid #e5e7eb;display:grid;gap:10px;margin-top:12px}
    .ptl-child{position:relative;border:1px solid #eceef2;border-radius:11px;background:#fff;padding:11px 12px}
    .ptl-child::before{content:'';position:absolute;left:-18px;top:16px;width:16px;height:2px;background:#e5e7eb}
    .ptl-child.is-latest{box-shadow:0 0 0 2px #7c3aed;border-color:#7c3aed}
    .ptl-child-order{position:absolute;left:-18px;top:11px;transform:translateX(-50%);width:22px;height:22px;border-radius:999px;background:#eef2ff;border:2px solid #fff;color:#4f46e5;font-size:9px;font-weight:900;display:flex;align-items:center;justify-content:center}
    .ptl-child-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;padding-bottom:9px;margin-bottom:9px;border-bottom:1px solid #f2f4f7}
    .ptl-child-meta strong{display:block;font-size:11px;color:#111827;margin-top:2px}
    .ptl-child-meta span{display:block;margin-top:3px;font-size:9px;color:#7c8492}
    .ptl-child-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(82px,1fr));gap:10px}
    .ptl-child-grid div span{display:block;font-size:8px;text-transform:uppercase;color:#98a2b3;font-weight:800;letter-spacing:.04em}
    .ptl-child-grid div strong{display:block;margin-top:3px;font-size:11px;color:#344054}

    .ptl-file{display:inline-flex;align-items:center;justify-content:center;min-height:28px;padding:6px 10px;border-radius:8px;text-decoration:none;font-size:8px;font-weight:850;white-space:nowrap;background:#fff;border:1px solid #d0d5dd;color:#344054}

    @media(max-width:700px){
        .ptl-branches{grid-template-columns:1fr}
        .ptl-spine::before,.ptl-branch::before{left:8px}
        .ptl-spine::after{left:8px;transform:none}
        .ptl-spine{height:12px}
        .ptl-branch::before{top:-12px;height:12px}
    }
    @media(max-width:560px){
        .ptl-root{max-width:100%}
        .ptl-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
</style>

@php($hasAnyRecords = $ptlSubmission !== null || $ptlBranches->isNotEmpty())

@if(! $hasAnyRecords)
    <div class="empty-state">No Progress Updates records have been submitted yet.</div>
@else
    @php($ptlChildrenAll = collect()
        ->merge($ptlSubmission['children'] ?? collect())
        ->merge($ptlBranches->flatMap(fn ($b) => $b['children'] ?? collect()))
        ->filter(fn ($c) => $c->submitted_at)
        ->sortByDesc('submitted_at')
        ->first())
    @php($ptlReview = app(\App\Services\DesignTaskReportingService::class))
    @php($ptlFinalAt = ($timeline['flow']['branches'] ?? collect())->firstWhere('type','final')['approvedAt'] ?? null)

    <div class="ptl-tree">
        {{-- Root node: Submission --}}
        <div class="ptl-root">
            <div class="ptl-root-head">
                <div class="ptl-root-title"><span class="dot"></span><strong>Submission</strong></div>
                <span class="ptl-root-taskid">{{ $task->task_id }}</span>
            </div>
            <div class="ptl-grid">
                <div class="ptl-cell wide"><span>Task Name</span><strong>{{ $task->display_task_name }}</strong></div>
                <div class="ptl-cell"><span>Task Assigned At</span><strong>{{ $task->assigned_at?->format('d M Y · h:i A') ?? '—' }}</strong></div>
                <div class="ptl-cell"><span>Task Due Date</span><strong>{{ $task->due_at?->format('d M Y · h:i A') ?? '—' }}</strong></div>
                <div class="ptl-cell"><span>Task Completed At</span><strong>{{ $ptlFinalAt?->format('d M Y · h:i A') ?? '—' }}</strong></div>
                <div class="ptl-cell wide">
                    <span>Completion Status</span>
                    @php($ptlStatusText = $ptlReview->completionStatus($task, $ptlFinalAt))
                    <strong class="{{ match(true) {
                        str_contains($ptlStatusText, 'On Time') => 'ptl-ok',
                        str_contains($ptlStatusText, 'overdue') && $task->status !== 'completed' => 'ptl-bad',
                        str_contains($ptlStatusText, 'after due date') => 'ptl-warn',
                        default => ''
                    } }}">{{ $ptlStatusText }}</strong>
                </div>
                <div class="ptl-cell"><span>Submitted By</span><strong>{{ $ptlSubmission['submittedBy'] ?? '—' }}</strong></div>
                <div class="ptl-cell"><span>BD Assigned / Reviewer</span><strong>{{ $task->assigner?->name ?? '—' }}</strong></div>
                <div class="ptl-cell"><span>Total Creatives</span><strong>{{ $task->total_creatives }}</strong></div>
                <div class="ptl-cell"><span>Completed</span><strong>{{ $ptlCompleted }}</strong></div>
                <div class="ptl-cell"><span>Remaining</span><strong class="{{ $ptlRemaining === 0 ? 'ptl-zero' : '' }}">{{ $ptlRemaining }}</strong></div>
                <div class="ptl-cell"><span>BD Review Started</span><strong>{{ $ptlSubmission['reviewStartedAt']?->format('d M Y · h:i A') ?? '—' }}</strong></div>
                <div class="ptl-cell"><span>BD Review Duration</span><strong>{{ $ptlSubmission['reviewDurationMinutes'] !== null ? $ptlReview->humanDuration($ptlSubmission['reviewDurationMinutes']) : '—' }}</strong></div>
            </div>

            @if(($ptlSubmission['children'] ?? collect())->isNotEmpty())
                <div class="ptl-children">
                    @foreach($ptlSubmission['children'] as $order => $child)
                        <div class="ptl-child {{ ! empty($child->submitted_at) && $ptlChildrenAll && $child->submitted_at->eq($ptlChildrenAll->submitted_at) ? 'is-latest' : '' }}">
                            <span class="ptl-child-order">{{ $order + 1 }}</span>
                            <div class="ptl-child-head">
                                <div class="ptl-child-meta">
                                    <strong>Submission {{ $order + 1 }}</strong>
                                    <span>by {{ $child->designer?->name ?? '—' }} · {{ $child->submitted_at?->format('d M Y · h:i A') }}</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    @if($ptlIsComplete && $ptlFinalZipUrl)
                                        <a class="ptl-file" target="_blank" href="{{ $ptlFinalZipUrl }}" title="Download final submission">⬇ Final ZIP</a>
                                    @elseif($child->attachment_url)
                                        <a class="ptl-file" target="_blank" href="{{ $child->attachment_url }}" title="Download">⬇ {{ $child->attachment_original_name ?? 'Download ZIP' }}</a>
                                    @else
                                        <span class="ptl-empty">No file available</span>
                                    @endif
                                </div>
                            </div>
                            <div class="ptl-child-grid">
                                <div><span>Submitted</span><strong>{{ $child->completed_count }}</strong></div>
                                <div><span>Completed</span><strong>{{ $child->cumulative_completed }} / {{ $child->total_creatives_snapshot }}</strong></div>
                                <div><span>Remaining</span><strong class="{{ $child->remaining_creatives === 0 ? 'ptl-zero' : '' }}">{{ $child->remaining_creatives }}</strong></div>
                                @if($child->time_taken_text)
                                    <div><span>Time Taken</span><strong>{{ $child->time_taken_text }}</strong></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="ptl-spine"></div>

        <div class="ptl-branches">
            @foreach($ptlBranches as $branch)
                <div class="ptl-branch">
                    @if($branch['type'] === 'rework')
                        <div class="ptl-card is-rework">
                            <div class="ptl-card-head">
                                <span class="ptl-badge is-rework">Rework #{{ $branch['ordinal'] }}</span>
                                <span class="ptl-card-meta">Moved {{ $branch['startedAt']?->format('d M Y · h:i A') ?? '—' }}</span>
                            </div>
                            <div class="ptl-grid">
                                <div class="ptl-cell"><span>Moved By</span><strong>{{ $branch['bdName'] ?? '—' }}</strong></div>
                                <div class="ptl-cell"><span>Rework Creatives</span><strong>{{ $branch['requestedCount'] }}</strong></div>
                                <div class="ptl-cell"><span>Rework Remaining</span><strong class="{{ $branch['remainingCount'] === 0 ? 'ptl-zero' : '' }}">{{ $branch['remainingCount'] }}</strong></div>
                                <div class="ptl-cell"><span>Designer Time</span><strong>{{ $branch['durationText'] }}</strong></div>
                            </div>

                            @if(($branch['children'] ?? collect())->isNotEmpty())
                                <div class="ptl-children">
                                    @foreach($branch['children'] as $order => $child)
                                        <div class="ptl-child {{ ! empty($child->submitted_at) && $ptlChildrenAll && $child->submitted_at->eq($ptlChildrenAll->submitted_at) ? 'is-latest' : '' }}">
                                            <span class="ptl-child-order">{{ $order + 1 }}</span>
                                            <div class="ptl-child-head">
                                                <div class="ptl-child-meta">
                                                    <strong>Rework Completion {{ $order + 1 }}</strong>
                                                    <span>by {{ $child->designer?->name ?? '—' }} · {{ $child->submitted_at?->format('d M Y · h:i A') }}</span>
                                                </div>
                                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                                    @if($child->attachment_url)
                                                        <a class="ptl-file" target="_blank" href="{{ $child->attachment_url }}" title="Download">⬇ {{ $child->attachment_original_name ?? 'Download ZIP' }}</a>
                                                    @else
                                                        <span class="ptl-empty">No file available</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="ptl-child-grid">
                                                <div><span>Submitted</span><strong>{{ $child->completed_count }}</strong></div>
                                                <div><span>Rework Completed</span><strong>{{ $child->cumulative_completed }} / {{ $branch['requestedCount'] }}</strong></div>
                                                <div><span>Rework Remaining</span><strong>{{ $child->remaining_creatives }}</strong></div>
                                                @if($child->time_taken_text)
                                                    <div><span>Time Taken</span><strong>{{ $child->time_taken_text }}</strong></div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="ptl-empty" style="margin-top:10px">No rework submission yet in this cycle.</div>
                            @endif
                        </div>

                    @elseif($branch['type'] === 'final')
                        <div class="ptl-card is-final">
                            <div class="ptl-card-head">
                                <span class="ptl-badge is-final">Final BD Approval</span>
                                <span class="ptl-card-meta">{{ $branch['status'] }}</span>
                            </div>
                            @if($ptlFinalZipUrl)
                                <div style="margin-bottom:12px">
                                    <a class="ptl-file" target="_blank" href="{{ $ptlFinalZipUrl }}" title="Download Final Submission">⬇ Download Final Submission</a>
                                </div>
                            @endif
                            <div class="ptl-grid">
                                <div class="ptl-cell"><span>Approved / Completed By</span><strong>{{ $branch['approvedBy'] }}</strong></div>
                                <div class="ptl-cell"><span>Approved At</span><strong>{{ $branch['approvedAt']?->format('d M Y · h:i A') ?? '—' }}</strong></div>
                                <div class="ptl-cell"><span>Final BD Review Duration</span><strong>{{ $branch['reviewDurationMinutes'] !== null ? $ptlReview->humanDuration($branch['reviewDurationMinutes']) : '—' }}</strong></div>
                                <div class="ptl-cell"><span>Total Rework Count</span><strong>{{ $branch['totalReworkCount'] }}</strong></div>
                                <div class="ptl-cell"><span>Total Rework Creatives</span><strong>{{ $branch['totalReworkCreatives'] }}</strong></div>
                                <div class="ptl-cell"><span>Total Designer Rework Time</span><strong>{{ $branch['totalDesignerReworkTime'] }}</strong></div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
