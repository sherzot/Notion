<?php

namespace App\Jobs;

use App\Models\EventLog;
use App\Services\TelegramNotifierService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramEventLogNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $eventLogId,
    ) {}

    public function handle(TelegramNotifierService $notifier): void
    {
        $eventLog = EventLog::find($this->eventLogId);
        if (! $eventLog) {
            return;
        }

        $notifier->notifyEventLog($eventLog);
    }
}

