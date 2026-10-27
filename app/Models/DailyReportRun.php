<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportRun extends Model
{
    protected $fillable = [
        'report_date',
        'trigger',
        'triggered_by',
        'idempotency_key',
        'timezone',
        'status',
        'attempts',
        'designers_evaluated',
        'active_designers_count',
        'submitters_count',
        'included_count',
        'no_tasks_count',
        'all_completed_count',
        'active_no_progress_count',
        'awaiting_review_count',
        'to_recipients',
        'cc_recipients',
        'report_snapshot',
        'api_status_code',
        'api_message',
        'last_attempt_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'report_snapshot' => 'array',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
