<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        // 受信した update はログに残す（後で user linking を実装する）
        Log::info('telegram_webhook_update', [
            'update' => $request->all(),
        ]);

        return response()->json(['ok' => true]);
    }
}

