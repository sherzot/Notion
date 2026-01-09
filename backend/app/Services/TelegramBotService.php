<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramBotService
{
    public function sendMessage(string $chatId, string $text): Response
    {
        $token = (string) config('services.telegram.token');
        $apiBase = rtrim((string) config('services.telegram.api_base'), '/');

        return Http::asJson()->post("{$apiBase}/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}

