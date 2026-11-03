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
        'extra_attachments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'extra_attachments' => 'array',
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

    /**
     * Every file of this submission, primary first, as [['url' => ..., 'name' => ...], ...].
     * Rows saved before multi-file support simply return their one file.
     */
    public function getAttachmentLinksAttribute(): array
    {
        $links = [];

        if ($this->attachment_url) {
            $links[] = ['url' => $this->attachment_url, 'name' => $this->attachment_original_name ?? 'Download'];
        }

        foreach ((array) $this->extra_attachments as $extra) {
            if (! empty($extra['path'])) {
                $links[] = [
                    'url' => \Illuminate\Support\Facades\Storage::disk($extra['disk'] ?? 'spaces')->url($extra['path']),
                    'name' => $extra['name'] ?? basename($extra['path']),
                ];
            }
        }

        return $links;
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
