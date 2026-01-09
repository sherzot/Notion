<?php

namespace App\Models;

use App\Jobs\SendTelegramEventLogNotification;
use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'entity_type',
        'entity_id',
        'payload_json',
        'telegram_sent_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'telegram_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (EventLog $eventLog): void {
            SendTelegramEventLogNotification::dispatch($eventLog->id);
        });
    }
}
