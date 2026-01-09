<?php

namespace App\Services;

use App\Models\EventLog;
use App\Models\TelegramTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TelegramNotifierService
{
    public function __construct(
        private readonly TelegramBotService $telegramBot,
    ) {}

    public function notifyEventLog(EventLog $eventLog): void
    {
        $token = (string) config('services.telegram.token');
        if ($token === '') {
            return;
        }

        $targets = TelegramTarget::query()
            ->where('user_id', $eventLog->user_id)
            ->where('enabled', true)
            ->get();

        if ($targets->isEmpty()) {
            return;
        }

        $text = $this->formatMessage($eventLog);

        $sentAny = false;

        foreach ($targets as $target) {
            try {
                $resp = $this->telegramBot->sendMessage($target->chat_id, $text);
                if ($resp->successful()) {
                    $sentAny = true;
                } else {
                    Log::warning('telegram_send_failed', [
                        'status' => $resp->status(),
                        'body' => $resp->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('telegram_send_exception', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($sentAny && $eventLog->telegram_sent_at === null) {
            $eventLog->forceFill([
                'telegram_sent_at' => Carbon::now(),
            ])->save();
        }
    }

    private function formatMessage(EventLog $eventLog): string
    {
        $type = $eventLog->type;
        $payload = $eventLog->payload_json ?? [];

        $title = $payload['title'] ?? null;

        return $title ? "{$type}\n{$title}" : $type;
    }
}

