{{--
    Shared Progress Updates parent/child tree — included by Designer, Designer
    Head and BD task detail pages so all three roles see the identical design.
    Expects: $timeline (from DesignTaskReportingService::progressTimeline()), $task.
    Total/Completed/Remaining/Rework Count are already shown by each page's
    existing "Overall Completion" summary cards immediately above this include,
    so this partial only adds what isn't already on screen: Assigned BD/Designer.
--}}
<style>
    .ptl-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:18px}
    .ptl-summary-item{padding:12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0}
    .ptl-summary-item span{display:block;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.05em;color:#7c8492}
    .ptl-summary-item strong{display:block;font-size:12px;margin-top:5px;color:#111827;overflow-wrap:anywhere}

    .ptl-parent{border:1px solid #e7e9ef;border-radius:13px;background:#fff;padding:14px;margin-bottom:14px}
    .ptl-parent.is-rework{border-color:#f2ce68;background:linear-gradient(180deg,#fffdf7,#fff9e8);box-shadow:inset 4px 0 0 #f5b301}
    .ptl-parent.is-initial{border-left:4px solid #d9dee7}
    .ptl-parent-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    .ptl-parent-badge{display:inline-flex;align-items:center;min-height:23px;padding:4px 10px;border-radius:999px;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.03em}
    .ptl-parent-badge.is-rework{background:#fff0c2;color:#7a5200;border:1px solid #f2cf68}
    .ptl-parent-badge.is-initial{background:#f2f4f7;color:#475467;border:1px solid #e4e7ec}
    .ptl-parent-meta{font-size:9px;color:#7c8492}
    .ptl-parent-grid{display:grid;grid-template-columns:repeat(3,minmax(90px,1fr));gap:10px;margin-bottom:12px}
    .ptl-parent-grid div span{display:block;font-size:8px;text-transform:uppercase;color:#98a2b3;font-weight:800;letter-spacing:.04em}
    .ptl-parent-grid div strong{display:block;margin-top:3px;font-size:12px;color:#344054}
    .ptl-zero{color:#15803d!important}

    .ptl-children{position:relative;margin-left:16px;padding-left:18px;border-left:2px solid #e5e7eb;display:grid;gap:10px}
    .ptl-child{position:relative;border:1px solid #eceef2;border-radius:11px;background:#fff;padding:11px 12px}
    .ptl-child::before{content:'';position:absolute;left:-18px;top:16px;width:16px;height:2px;background:#e5e7eb}
    .ptl-child-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;padding-bottom:9px;margin-bottom:9px;border-bottom:1px solid #f2f4f7}
    .ptl-child-meta strong{display:block;font-size:11px;color:#111827;margin-top:2px}
    .ptl-child-meta span{display:block;margin-top:3px;font-size:9px;color:#7c8492}
    .ptl-child-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(90px,1fr));gap:10px}
    .ptl-child-grid div span{display:block;font-size:8px;text-transform:uppercase;color:#98a2b3;font-weight:800;letter-spacing:.04em}
    .ptl-child-grid div strong{display:block;margin-top:3px;font-size:11px;color:#344054}
    .ptl-file{display:inline-flex;align-items:center;justify-content:center;min-height:28px;padding:6px 10px;border-radius:8px;text-decoration:none;font-size:8px;font-weight:850;white-space:nowrap;background:#fff;border:1px solid #d0d5dd;color:#344054}

    @media(max-width:900px){.ptl-parent-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:560px){.ptl-summary{grid-template-columns:1fr}}
</style>

<div class="ptl-summary">
    <div class="ptl-summary-item"><span>Assigned BD</span><strong>{{ $task->assigner?->name ?? '—' }}</strong></div>
    <div class="ptl-summary-item"><span>Assigned Designer</span><strong>{{ $task->designer?->name ?? '—' }}</strong></div>
</div>

@php($hasAnyRecords = $timeline['initial']->isNotEmpty() || $timeline['reworks']->isNotEmpty())

@if(! $hasAnyRecords)
    <div class="empty-state">No Progress Updates records have been submitted yet.</div>
@else
    @foreach($timeline['reworks'] as $parent)
        <div class="ptl-parent is-rework">
            <div class="ptl-parent-head">
                <span class="ptl-parent-badge is-rework">Rework #{{ $parent['ordinal'] }}</span>
                <span class="ptl-parent-meta">
                    Started {{ $parent['startedAt']->format('d M Y · h:i A') }} by {{ $parent['bdName'] ?? '—' }}
                </span>
            </div>
            <div class="ptl-parent-grid">
                <div><span>Rework Creatives</span><strong>{{ $parent['requestedCount'] }}</strong></div>
                <div><span>Remaining</span><strong class="{{ $parent['remainingCount'] === 0 ? 'ptl-zero' : '' }}">{{ $parent['remainingCount'] }}</strong></div>
                <div><span>Duration</span><strong>{{ $parent['durationText'] }}</strong></div>
            </div>

            <div class="ptl-children">
                @foreach($parent['children'] as $child)
                    <div class="ptl-child">
                        <div class="ptl-child-head">
                            <div class="ptl-child-meta">
                                <strong>Submitted by {{ $child->designer?->name ?? '—' }}</strong>
                                <span>{{ $child->submitted_at?->format('d M Y · h:i A') }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                @if($child->attachment_url)
                                    <a class="ptl-file" target="_blank" href="{{ $child->attachment_url }}" title="Download">⬇ {{ $child->attachment_original_name ?? 'Download ZIP' }}</a>
                                @else
                                    <span class="muted" style="font-size:9px">No file available</span>
                                @endif
                            </div>
                        </div>
                        <div class="ptl-child-grid">
                            <div><span>Submitted</span><strong>{{ $child->completed_count }}</strong></div>
                            <div><span>Rework Completed</span><strong>{{ $child->cumulative_completed }} / {{ $parent['requestedCount'] }}</strong></div>
                            <div><span>Rework Remaining</span><strong>{{ $child->remaining_creatives }}</strong></div>
                            @if($child->time_taken_text)
                                <div><span>Time Taken</span><strong>{{ $child->time_taken_text }}</strong></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="ptl-parent is-initial">
        <div class="ptl-parent-head">
            <span class="ptl-parent-badge is-initial">Initial Progress</span>
        </div>

        @if($timeline['initial']->isEmpty())
            <div class="empty-state">No progress submissions yet.</div>
        @else
            <div class="ptl-children">
                @foreach($timeline['initial'] as $child)
                    <div class="ptl-child">
                        <div class="ptl-child-head">
                            <div class="ptl-child-meta">
                                <strong>Submitted by {{ $child->designer?->name ?? '—' }}</strong>
                                <span>{{ $child->submitted_at?->format('d M Y · h:i A') }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                @if($child->attachment_url)
                                    <a class="ptl-file" target="_blank" href="{{ $child->attachment_url }}" title="Download">⬇ {{ $child->attachment_original_name ?? 'Download ZIP' }}</a>
                                @else
                                    <span class="muted" style="font-size:9px">No file available</span>
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
@endif
