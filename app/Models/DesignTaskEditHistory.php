<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignTaskEditHistory extends Model
{
    protected $fillable = ['design_task_id','edited_by','edit_batch_id','field_name','old_value','new_value'];
    public function task(): BelongsTo { return $this->belongsTo(DesignTask::class, 'design_task_id'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'edited_by'); }
}
