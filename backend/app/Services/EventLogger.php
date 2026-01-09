<?php

namespace App\Services;

use App\Models\EventLog;
use App\Models\User;

class EventLogger
{
    /**
     * @param array<string,mixed>|null $payload
     */
    public function log(User $user, string $type, string $entityType, int $entityId, ?array $payload = null): EventLog
    {
        return EventLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload_json' => $payload,
        ]);
    }
}

