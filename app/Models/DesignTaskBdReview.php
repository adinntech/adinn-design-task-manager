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
}
