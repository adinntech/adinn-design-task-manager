<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileUpload extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'design_task_id', 'purpose', 'original_filename',
        'size_bytes', 'mime_type', 'cloud_disk', 'cloud_key', 'multipart_upload_id',
        'part_size_bytes', 'uploaded_bytes', 'status', 'completed_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DesignTask::class, 'design_task_id');
    }
}
