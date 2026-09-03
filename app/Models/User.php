<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
    use HasFactory, Notifiable;
    protected $fillable=['name','username','employee_code','email','password','role','is_active','last_login_at'];
    protected $hidden=['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean','last_login_at'=>'datetime']; }
    public function assignedTasks(){ return $this->hasMany(DesignTask::class,'designer_id'); }
    public function createdTasks(){ return $this->hasMany(DesignTask::class,'assigned_by'); }
}
