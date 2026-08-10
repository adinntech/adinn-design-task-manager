<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignTaskEodRecord extends Model
{
    protected $fillable = [
        'design_task_id',
        'designer_id',
        'completed_count',
        'total_creatives_snapshot',
        'cumulative_completed',
        'remaining_creatives',
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
}
