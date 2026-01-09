<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramTarget;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;

class TelegramTestController extends Controller
{
    public function __invoke(Request $request, TelegramBotService $bot)
    {
        $token = (string) config('services.telegram.token');
        if ($token === '') {
            return response()->json(['message' => 'TELEGRAM_BOT_TOKEN is not set'], 400);
        }

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:4096'],
        ]);

        $targets = TelegramTarget::query()
            ->where('user_id', $request->user()->id)
            ->where('enabled', true)
            ->get();

        if ($targets->isEmpty()) {
            return response()->json(['message' => 'No enabled telegram targets'], 400);
        }

        $text = $data['text'] ?? 'Test message from Notion Mini';

        $results = [];

        foreach ($targets as $t) {
            $resp = $bot->sendMessage($t->chat_id, $text);
            $results[] = [
                'id' => $t->id,
                'type' => $t->type,
                'chat_id' => $t->chat_id,
                'ok' => $resp->successful(),
                'status' => $resp->status(),
                'body' => $resp->json(),
            ];
        }

        return response()->json(['results' => $results]);
    }
}

