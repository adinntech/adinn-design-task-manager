{{-- Completed Task Ratings rows + pagination — swapped in/out by its own Designer filter/pager. No <style>/<script>, safe to AJAX-swap. --}}
@php
    $fmt = fn ($value) => $value === null ? '—' : \App\Models\DesignTaskBdReview::formatRating($value);
    $star = function ($value) {
        $rounded = max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($value)));
        $html = '<span class="dh-stars" aria-label="'.number_format($rounded, 1).' out of 5">';
        for ($i = 1; $i <= 5; $i++) {
            $fill = $rounded >= $i ? 100 : ($rounded >= $i - 0.5 ? 50 : 0);
            $html .= '<span class="dh-star" style="--star-fill:'.$fill.'%">★</span>';
        }
        return $html.'</span>';
    };

    $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
    $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);

    // Condensed page list: first, last, and a window around the current page —
    // with "…" filled in for any gap, same idea as the screenshot's "1 2 3 … 7".
    $shown = collect([1, $lastPage]);
    for ($p = $page - 2; $p <= $page + 2; $p++) {
        if ($p >= 1 && $p <= $lastPage) {
            $shown->push($p);
        }
    }
    $shown = $shown->unique()->sort()->values();
@endphp

<div class="dh-card-sub" style="padding:0 13px 10px">Data for {{ $monthLabel }} • {{ $designerName ?? 'All Designers' }}</div>
<div class="dhr-scroll">
    <div class="dhr-list">
        @forelse($rows as $rating)
            @php
                $task = $rating->task;
                $categories = [
                    'DA' => ['Designer Attitude', $rating->designer_attitude],
                    'DS' => ['Design Satisfaction', $rating->design_satisfaction],
                    'RI' => ['Rework Iteration', $rating->rework_iteration],
                    'MD' => ['Meeting Deadline', $rating->meeting_deadline],
                    'CS' => ['Client Satisfaction', $rating->client_satisfaction],
                    'OR' => ['Overall Rating', $rating->overall_rating],
                ];
                $completedAt = $completedAtByTask->get((int) $rating->design_task_id);
                $hasComment = filled($rating->comment);
                $longComment = $hasComment && mb_strlen($rating->comment) > 90;
            @endphp
            <div class="dhr-row">
                <div class="dhr-col dhr-col-task">
                    <div class="dhr-task-name">
                        @if($task)
                            <a class="dh-task-link" href="{{ route('designer-head.assigned-tasks', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a> — {{ $task->display_task_name ?? $task->task_name }}
                        @else
                            Task unavailable
                        @endif
                    </div>
                    <div class="dhr-designer-name">{{ $task?->designer?->name ?? '—' }}</div>
                    @if($completedAt)<div class="dhr-completed-date">completed {{ $completedAt->format('d M Y') }}</div>@endif
                </div>
                <div class="dhr-col dhr-col-ratings">
                    <div class="dhr-overall" title="Overall Rating — {{ $fmt($rating->overall_rating) }} / 5">
                        <div class="dhr-overall-value">{{ $fmt($rating->overall_rating) }} <span>/ 5</span></div>
                        {!! $star($rating->overall_rating) !!}
                    </div>
                    <div class="dhr-chips">
                        @foreach($categories as $code => [$label, $value])
                            <div class="dhr-chip" title="{{ $label }} — {{ $fmt($value) }} / 5">
                                <span class="dhr-chip-label">{{ $code }}</span>
                                <span class="dhr-chip-value">{{ $fmt($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="dhr-col dhr-col-comment">
                    @if($hasComment)
                        <div class="dhr-comment-label">BD Comment:</div>
                        <div class="dhr-comment-text">{{ $rating->comment }}</div>
                        @if($longComment)
                            <button type="button" class="dhr-comment-toggle" data-dhr-comment-toggle>View full comment</button>
                        @endif
                    @endif
                </div>
                <div class="dhr-col dhr-col-by">
                    <div class="dhr-by-name">{{ $rating->submitter?->name ?? 'BD' }}</div>
                    <div class="dhr-by-date">{{ optional($rating->created_at)->format('d M Y · h:i A') }}</div>
                </div>
            </div>
        @empty
            <div class="dh-empty">No completed-task ratings available.</div>
        @endforelse
    </div>
</div>
<div class="dhr-foot">
    <div class="dhr-foot-info">
        @if($total > 0)
            Showing {{ $from }} to {{ $to }} of {{ $total }} tasks
        @else
            No completed tasks
        @endif
    </div>
    @if($lastPage > 1)
        <div class="dhr-pager">
            <button type="button" data-dhr-page="{{ max(1, $page - 1) }}" @disabled($page <= 1)>&lsaquo;</button>
            @php $prev = null; @endphp
            @foreach($shown as $p)
                @if($prev !== null && $p - $prev > 1)
                    <span class="dhr-page-ellipsis">…</span>
                @endif
                <button type="button" class="{{ $p === $page ? 'dhr-page-active' : '' }}" data-dhr-page="{{ $p }}">{{ $p }}</button>
                @php $prev = $p; @endphp
            @endforeach
            <button type="button" data-dhr-page="{{ min($lastPage, $page + 1) }}" @disabled($page >= $lastPage)>&rsaquo;</button>
        </div>
    @endif
</div>
