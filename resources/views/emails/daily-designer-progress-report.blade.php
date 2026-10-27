{{-- Admin → Daily Reporting nightly mail. All values are Blade-escaped. --}}
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6;">
    <p>Hi team,</p>

    @if(empty($report['included']))
        <p>All designers submitted at least one progress update today.</p>
    @else
        <p>The following designers did not submit any progress update today ({{ $displayDate }}).</p>

        <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; border: 1px solid #d0d5dd; font-size: 13px;">
            <thead>
                <tr style="background: #f2f4f7; text-align: left;">
                    <th style="border: 1px solid #d0d5dd;">#</th>
                    <th style="border: 1px solid #d0d5dd;">Designer Name</th>
                    <th style="border: 1px solid #d0d5dd;">Employee ID</th>
                    <th style="border: 1px solid #d0d5dd;">Pending Task Count</th>
                    <th style="border: 1px solid #d0d5dd;">Pending Tasks / Current Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['included'] as $index => $row)
                    <tr style="vertical-align: top;">
                        <td style="border: 1px solid #d0d5dd;">{{ $index + 1 }}</td>
                        <td style="border: 1px solid #d0d5dd;">{{ $row['name'] }}</td>
                        <td style="border: 1px solid #d0d5dd;">{{ $row['employee_code'] ?: '—' }}</td>
                        <td style="border: 1px solid #d0d5dd;">{{ $row['active_task_count'] }}</td>
                        <td style="border: 1px solid #d0d5dd;">
                            @if($row['note'])
                                <div><em>{{ $row['note'] }}</em></div>
                            @endif
                            @foreach($row['tasks'] as $task)
                                <div>{{ $task['task_id'] }} — {{ $task['task_name'] }} <strong>({{ $task['status_label'] }})</strong></div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 20px; color: #667085; font-size: 12px;">
        Report window: {{ $displayDate }}, 00:00 – 23:59 ({{ $report['timezone'] }}).
        Designers evaluated: {{ $report['designers_evaluated'] }} · With pending tasks: {{ $report['active_designers'] ?? '—' }} · Submitted a progress update: {{ $report['submitters_count'] }} · Listed: {{ count($report['included']) }}@isset($report['counts']['no_pending_tasks']) ({{ $report['counts']['no_pending_tasks'] }} with no pending tasks)@endisset.
    </p>
</body>
</html>
