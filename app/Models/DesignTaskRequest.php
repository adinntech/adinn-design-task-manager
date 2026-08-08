<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'approved_designer_id',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(DesignTask::class, 'design_task_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function targetDesigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_designer_id');
    }

    public function approvedDesigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_designer_id');
    }

    public function designerHeadActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_head_action_by');
    }

    public function adminActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_action_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('overall_status', ['pending_approval', 'pending_designer_head', 'pending_admin']);
    }

    public function getStatusLabelAttribute(): string
    {
        return in_array($this->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true)
            ? 'Pending Approval'
            : ucwords(str_replace('_', ' ', $this->overall_status));
    }

    public function getDecidedByAttribute(): ?User
    {
        return $this->adminActor ?: $this->designerHeadActor;
    }
}
