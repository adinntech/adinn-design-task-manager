<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = ['user_id', 'otp_hash', 'attempts', 'expires_at', 'requested_ip'];

    protected $hidden = ['otp_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
