<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DesignTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id','assigned_at','assigned_by','task_name','vertical','task_nature','party_type','party_name',
        'contact_person','mobile_number','priority','due_at','designer_id','total_creatives','status','requirements',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'requirements' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function designer(){ return $this->belongsTo(User::class, 'designer_id'); }
    public function assigner(){ return $this->belongsTo(User::class, 'assigned_by'); }
    public function requests(){ return $this->hasMany(DesignTaskRequest::class, 'design_task_id'); }
    public function eodRecords(){ return $this->hasMany(DesignTaskEodRecord::class, 'design_task_id'); }

    public function getDisplayTaskNameAttribute(): string
    {
        return trim((string) preg_replace('/\s*\((?:split|swap|swapped)\)\s*$/i', '', (string) $this->task_name));
    }

    public function getOperationPillsAttribute(): array
    {
        $pills = [];
        $requirements = $this->requirements ?? [];

        // A child created by an approved split always carries Split Approved context.
        if (! empty($requirements['_split_request_id']) || ! empty($requirements['_split_from_task_id'])) {
            $pills['split'] = [
                'label' => 'Split Approved',
                'class' => 'task-operation-pill task-operation-pill-split task-operation-pill-approved',
            ];
        }

        $requests = $this->relationLoaded('requests')
            ? $this->requests
            : $this->requests()->whereIn('request_type', ['split', 'swap'])->latest('created_at')->get();

        foreach (['split', 'swap'] as $type) {
            $approved = $requests->first(fn ($request) => $request->request_type === $type && $request->overall_status === 'approved');
            if ($approved) {
                $pills[$type] = [
                    'label' => $type === 'split' ? 'Split Approved' : 'Swap Approved',
                    'class' => 'task-operation-pill task-operation-pill-'.$type.' task-operation-pill-approved',
                ];
                continue;
            }

            $latest = $requests->first(fn ($request) => $request->request_type === $type);
            if (! $latest) continue;

            $pending = in_array($latest->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
            $rejected = $latest->overall_status === 'rejected';

            if ($pending) {
                $pills[$type] = [
                    'label' => ucfirst($type).' Pending',
                    'class' => 'task-operation-pill task-operation-pill-pending',
                ];
            } elseif ($rejected) {
                $pills[$type] = [
                    'label' => ucfirst($type).' Declined',
                    'class' => 'task-operation-pill task-operation-pill-declined',
                ];
            }
        }

        return array_values($pills);
    }
}
