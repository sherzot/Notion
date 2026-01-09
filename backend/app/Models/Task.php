<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'status',
        'due_at',
        'source',
        'link',
    ];

    protected $casts = [
        'due_at' => 'datetime',
    ];
}
