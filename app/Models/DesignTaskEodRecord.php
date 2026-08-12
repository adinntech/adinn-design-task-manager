<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignTaskEodRecord extends Model
{
    protected $fillable = [
        'design_task_id',
        'designer_id',
        'update_type',
        'completed_count',
        'total_creatives_snapshot',
        'cumulative_completed',
        'remaining_creatives',
        'rework_count_snapshot',
        'attachment_disk',
        'attachment_path',
        'attachment_original_name',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(DesignTask::class, 'design_task_id');
    }

    public function designer()
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk($this->attachment_disk ?: 'spaces')
            ->url($this->attachment_path);
    }
}
