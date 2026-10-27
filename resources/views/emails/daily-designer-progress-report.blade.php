{{-- Admin → Daily Reporting nightly mail. All values are Blade-escaped. --}}
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6;">
    <p>Hi team,</p>

    @if(empty($report['included']))
        <p>All designers submitted progress today.</p>
    @else
        <p>The following designers did not submit any progress today ({{ $displayDate }}):</p>

        <ol>
            @foreach($report['included'] as $row)
                <li>{{ $row['name'] }}</li>
            @endforeach
        </ol>
    @endif

    <p style="margin-top: 20px; color: #667085; font-size: 12px;">
        Report window: {{ $displayDate }}, 00:00 – 23:59 ({{ $report['timezone'] }}).
        Designers: {{ $report['designers_evaluated'] }} · Submitted progress: {{ $report['submitters_count'] }} · Not submitted: {{ count($report['included']) }}.
    </p>
</body>
</html>
