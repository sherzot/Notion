<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramTarget extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'chat_id',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
