<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class DesignTask extends Model {
    use HasFactory;
    protected $fillable=['task_id','assigned_at','assigned_by','task_name','vertical','task_nature','party_type','party_name','contact_person','mobile_number','priority','due_at','designer_id','total_creatives','status','requirements'];
    protected function casts(): array { return ['assigned_at'=>'datetime','due_at'=>'datetime','requirements'=>'array']; }
    public function designer(){ return $this->belongsTo(User::class,'designer_id'); }
    public function assigner(){ return $this->belongsTo(User::class,'assigned_by'); }
    public function requests(){ return $this->hasMany(DesignTaskRequest::class,'design_task_id'); }

    public function getDisplayTaskNameAttribute(): string
    {
        return trim((string) preg_replace('/\s*\((?:split|swap|swapped)\)\s*$/i', '', (string) $this->task_name));
    }

    public function getOperationPillsAttribute(): array
    {
        $pills = [];
        $requirements = $this->requirements ?? [];

        if (!empty($requirements['_split_request_id']) || !empty($requirements['_split_from_task_id'])) {
            $pills['split'] = ['label' => 'Split', 'class' => 'task-operation-pill task-operation-pill-split'];
        }

        $requests = $this->relationLoaded('requests')
            ? $this->requests
            : $this->requests()->whereIn('request_type', ['split', 'swap'])->latest('created_at')->get();

        foreach ($requests as $request) {
            if ($request->overall_status !== 'approved') {
                continue;
            }

            if ($request->request_type === 'split') {
                $pills['split'] = ['label' => 'Split', 'class' => 'task-operation-pill task-operation-pill-split'];
            } elseif ($request->request_type === 'swap') {
                $pills['swap'] = ['label' => 'Swapped', 'class' => 'task-operation-pill task-operation-pill-swap'];
            }
        }

        return array_values($pills);
    }
}
