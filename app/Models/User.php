<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'employee_code', 'email', 'password', 'role', 'role_name', 'phone_number', 'is_active', 'can_approve_requests', 'web_push_enabled', 'last_login_at', 'experienced_verticals', 'skills', 'designer_head_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_approve_requests' => 'boolean',
            'web_push_enabled' => 'boolean',
            'last_login_at' => 'datetime',
            'experienced_verticals' => 'array',
            'skills' => 'array',
        ];
    }

    public function assignedTasks()
    {
        return $this->hasMany(DesignTask::class, 'designer_id');
    }

    public function createdTasks()
    {
        return $this->hasMany(DesignTask::class, 'assigned_by');
    }

    public function designerHead()
    {
        return $this->belongsTo(User::class, 'designer_head_id');
    }

    /**
     * A Designer the Admin has allowed to approve/decline Swap & Split requests
     * raised by teammates (see DesignTaskRequestService::guardApprover()).
     */
    public function isRequestApprover(): bool
    {
        return $this->role === 'designer' && $this->is_active && (bool) $this->can_approve_requests;
    }

    public function managedDesigners()
    {
        return $this->hasMany(User::class, 'designer_head_id');
    }
}
