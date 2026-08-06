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
}
