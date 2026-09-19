<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllUsersMail extends Model
{
    protected $table = 'all_users_mail';

    protected $fillable = [
        'name',
        'mail',
    ];
}
