{{-- Admin → Daily Reporting nightly mail. All values are Blade-escaped. --}}
@php
    $th = 'border: 1px solid #d0d5dd; padding: 7px 9px; text-align: left;';
    $td = 'border: 1px solid #d0d5dd; padding: 7px 9px; vertical-align: top;';
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6;">
    <p>Hi team,</p>

    @foreach([
        ['rows' => $report['included'], 'title' => "The following designers did not submit any progress today ({$displayDate}):", 'empty' => 'All designers submitted progress today.'],
        ['rows' => $report['submitted'] ?? [], 'title' => "Designers who submitted progress today ({$displayDate}):", 'empty' => 'No designer submitted progress today.'],
    ] as $section)
        @if(empty($section['rows']))
            <p style="margin-top: 20px;"><strong>{{ $section['empty'] }}</strong></p>
        @else
            <p style="margin-top: 20px;"><strong>{{ $section['title'] }}</strong></p>
            <table cellpadding="0" cellspacing="0" style="border-collapse: collapse; border: 1px solid #d0d5dd; font-size: 13px;">
                <thead>
                    <tr style="background: #f2f4f7;">
                        <th style="{{ $th }}">#</th>
                        <th style="{{ $th }}">Designer Name</th>
                        <th style="{{ $th }}">Total Tasks</th>
                        <th style="{{ $th }}">Tasks / Current Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['rows'] as $index => $row)
                        <tr>
                            <td style="{{ $td }}">{{ $index + 1 }}</td>
                            <td style="{{ $td }}">{{ $row['name'] }}</td>
                            <td style="{{ $td }} text-align: center;">{{ $row['total_tasks'] ?? 0 }}</td>
                            <td style="{{ $td }}">
                                @foreach($row['status_counts'] ?? [] as $c)
                                    <span style="white-space: nowrap; {{ $c['count'] > 0 ? 'font-weight: bold;' : 'color: #98a2b3;' }}">{{ $c['label'] }} ({{ $c['count'] }})</span>@if(! $loop->last) · @endif
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <p style="margin-top: 20px; color: #667085; font-size: 12px;">
        Report window: {{ $displayDate }}, 00:00 – 23:59 ({{ $report['timezone'] }}). Task counts are as of when the report was generated.
        Designers: {{ $report['designers_evaluated'] }} · Submitted progress: {{ $report['submitters_count'] }} · Not submitted: {{ count($report['included']) }}.
    </p>
</body>
</html>
