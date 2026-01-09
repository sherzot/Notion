<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramTarget;
use Illuminate\Http\Request;

class TelegramTargetController extends Controller
{
    public function index(Request $request)
    {
        $targets = TelegramTarget::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['telegram_targets' => $targets]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:channel,private'],
            'chat_id' => ['required', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $chatId = trim($data['chat_id']);

        $target = TelegramTarget::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'chat_id' => $chatId,
            ],
            [
                'enabled' => $data['enabled'] ?? true,
            ]
        );

        return response()->json(
            ['telegram_target' => $target],
            $target->wasRecentlyCreated ? 201 : 200
        );
    }

    public function update(Request $request, TelegramTarget $telegramTarget)
    {
        abort_unless($telegramTarget->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $telegramTarget->update($data);

        return response()->json(['telegram_target' => $telegramTarget]);
    }

    public function destroy(Request $request, TelegramTarget $telegramTarget)
    {
        abort_unless($telegramTarget->user_id === $request->user()->id, 404);

        $telegramTarget->delete();

        return response()->json(['ok' => true]);
    }
}

