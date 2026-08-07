<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignTaskRequest extends Model
{
    protected $fillable = [
        'design_task_id',
        'request_type',
        'requested_by',
        'designer_head_status',
        'designer_head_action_by',
        'designer_head_action_at',
        'admin_status',
        'admin_action_by',
        'admin_action_at',
        'overall_status',
        'reason',
        'target_designer_id',
        'split_details',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'designer_head_action_at' => 'datetime',
            'admin_action_at' => 'datetime',
            'split_details' => 'array',
            'attachments' => 'array',
        ];
    }

    public function task()
    {
        return $this->belongsTo(DesignTask::class, 'design_task_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function targetDesigner()
    {
        return $this->belongsTo(User::class, 'target_designer_id');
    }
}
