<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->first()?->value ?? $default;
    }

    public static function putValue(string $key, mixed $value, ?int $userId = null): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
    }
}
