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
        $payload = $eventLog->payload_json ?? [];

        $lines = [];
        $lines[] = $eventLog->type;
        $lines[] = "id: {$eventLog->entity_id}";

        $order = [
            'title',
            'body',
            'tags',
            'status',
            'due_at',
            'start_at',
            'end_at',
            'remind_before_minute',
            'source',
            'link',
            'related_type',
            'related_id',
        ];

        foreach ($order as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $val = $payload[$key];
            if ($val === null || $val === '') {
                continue;
            }
            if (is_array($val)) {
                if (count($val) === 0) {
                    continue;
                }
                $val = implode(', ', array_map('strval', $val));
            }
            $lines[] = "{$key}: {$val}";
        }

        return implode("\n", $lines);
    }
}

