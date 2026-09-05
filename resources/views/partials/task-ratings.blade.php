{{--
    Shared Ratings tab body for BD / Designer / Designer Head Ticket Details.
    Expects: $taskRating (DesignTaskBdReview|null, action = 'completed').
--}}
@if(! $taskRating)
    <div class="empty-state">No rating available.</div>
@else
    @php
        $ratingItems = [
            ['label' => 'Designer Attitude', 'value' => $taskRating->designer_attitude, 'icon' => '👤', 'bg' => '#eaf2ff', 'fg' => '#2563eb'],
            ['label' => 'Design Satisfaction', 'value' => $taskRating->design_satisfaction, 'icon' => '🎯', 'bg' => '#fdeef5', 'fg' => '#db2777'],
            ['label' => 'Rework Iteration', 'value' => $taskRating->rework_iteration, 'icon' => '🔁', 'bg' => '#eafbf0', 'fg' => '#16a34a'],
            ['label' => 'Meeting Deadline', 'value' => $taskRating->meeting_deadline, 'icon' => '📅', 'bg' => '#fff1e6', 'fg' => '#ea580c'],
            ['label' => 'Client Satisfaction', 'value' => $taskRating->client_satisfaction, 'icon' => '👥', 'bg' => '#f3eefe', 'fg' => '#7c3aed'],
            ['label' => 'Overall Rating', 'value' => $taskRating->overall_rating, 'icon' => '📊', 'bg' => '#eef1f5', 'fg' => '#475467'],
        ];
    @endphp

    <div class="ratings-card">
        <div class="ratings-grid">
            @foreach($ratingItems as $item)
                @php $ratingValue = max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($item['value']))); @endphp
                <div class="ratings-grid-item">
                    <div class="ratings-item-left">
                        <span class="ratings-item-icon" style="background:{{ $item['bg'] }};color:{{ $item['fg'] }}">{{ $item['icon'] }}</span>
                        <div>
                            <div class="ratings-item-name">{{ $item['label'] }}</div>
                            <div class="ratings-item-stars" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                @for($starIndex = 1; $starIndex <= 5; $starIndex++)
                                    @php $starFill = $ratingValue >= $starIndex ? 100 : ($ratingValue >= ($starIndex - 0.5) ? 50 : 0); @endphp
                                    <span class="rating-static-star" style="--star-fill:{{ $starFill }}%;" aria-hidden="true">★</span>
                                @endfor
                            </div>
                        </div>
                    </div>
                    <div class="ratings-item-score">{{ \App\Models\DesignTaskBdReview::formatRating($ratingValue) }} / 5</div>
                </div>
            @endforeach
        </div>

        <div class="ratings-footer">
            <div class="ratings-footer-comments">
                <span class="ratings-footer-icon is-comment">💬</span>
                <div>
                    <div class="ratings-footer-title">Comments</div>
                    <div class="ratings-footer-text">{{ $taskRating->comment ?: 'No comments added.' }}</div>
                </div>
            </div>
            <div class="ratings-footer-submitted">
                <span class="ratings-footer-icon is-user">👤</span>
                <div>
                    <div class="ratings-footer-label">Submitted by</div>
                    <div class="ratings-footer-name">{{ $taskRating->submitter?->name ?? 'BD' }}</div>
                    <div class="ratings-footer-date">{{ $taskRating->created_at?->format('d M Y, h:i A') }}</div>
                </div>
            </div>
        </div>
    </div>
@endif
