<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityFlag extends Model
{
    protected $fillable = ['user_id', 'scope', 'flagged_at'];

    protected function casts(): array
    {
        return ['flagged_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
