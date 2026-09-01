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
        'decision_reason',
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

    /**
     * The new task created by an approved 'split' request. Not a DB relation —
     * only the child's id is stored, in split_details.created_task_id (see
     * DesignTaskRequestService::executeSplit()).
     */
    public function createdSplitTask(): ?DesignTask
    {
        $id = data_get($this->split_details, 'created_task_id');

        return $id ? DesignTask::find($id) : null;
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

    public function getRespondedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->adminActor ? $this->admin_action_at : $this->designer_head_action_at;
    }

    /**
     * Creative-count figures for a 'split' request, derived from split_details
     * (see DesignTaskRequestService::executeSplit()). Total is reconstructed
     * from the immutable approved_creative_count + original_remaining_creatives
     * pair recorded at execution time — never from the task's current (possibly
     * further-changed) total_creatives — so it stays accurate historically.
     * Before approval only the requested count is known; approved/remaining/
     * percent stay null rather than guessing.
     */
    public function splitCreativeSummary(): array
    {
        $details = $this->split_details ?? [];
        $requested = $details['requested_creative_count'] ?? $details['creative_count'] ?? null;
        $approved = $details['approved_creative_count'] ?? null;
        $keptByOriginal = $details['original_remaining_creatives'] ?? null;

        $total = ($approved !== null && $keptByOriginal !== null)
            ? $approved + $keptByOriginal
            : $this->task?->total_creatives;

        $remaining = ($approved !== null && $total !== null) ? max(0, $total - $approved) : null;
        $percent = ($approved !== null && $total) ? (int) round(($approved / $total) * 100) : null;

        return [
            'total' => $total,
            'requested' => $requested,
            'approved' => $approved,
            'remaining' => $remaining,
            'percent' => $percent,
        ];
    }
}
