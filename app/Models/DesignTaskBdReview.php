<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignTaskBdReview extends Model
{
    protected $fillable = [
        'design_task_id',
        'submitted_by',
        'action',
        'number_of_creatives',
        'comment',
        'attachment_disk',
        'attachment_path',
        'attachment_original_name',
        'attachment_size',
        'designer_attitude',
        'design_satisfaction',
        'rework_iteration',
        'meeting_deadline',
        'client_satisfaction',
        'overall_rating',
    ];

    protected function casts(): array
    {
        return [
            'designer_attitude' => 'decimal:1',
            'design_satisfaction' => 'decimal:1',
            'rework_iteration' => 'decimal:1',
            'meeting_deadline' => 'decimal:1',
            'client_satisfaction' => 'decimal:1',
            'overall_rating' => 'decimal:2',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DesignTask::class, 'design_task_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk($this->attachment_disk ?: 'spaces')
            ->url($this->attachment_path);
    }

    /**
     * Snap any rating value to the nearest 0.5, so display never shows a raw
     * average like 4.7 — only 0.5/1/1.5.../5.
     */
    public static function roundToHalfStar(float|int|string|null $value): float
    {
        return round(((float) $value) * 2) / 2;
    }

    public static function formatRating(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format(self::roundToHalfStar($value), 2), '0'), '.');
    }
}
