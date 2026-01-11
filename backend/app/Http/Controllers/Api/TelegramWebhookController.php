<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Note;
use App\Models\Task;
use App\Models\TelegramTarget;
use App\Models\User;
use App\Services\AIService;
use App\Services\EventLogger;
use App\Services\TelegramBotService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, AIService $ai, TelegramBotService $telegram, EventLogger $eventLogger)
    {
        $update = $request->all();

        // Keep a minimal log for debugging (avoid storing huge payloads).
        Log::info('telegram_webhook_update', [
            'update_id' => $update['update_id'] ?? null,
            'has_message' => isset($update['message']),
            'has_channel_post' => isset($update['channel_post']),
        ]);

        $message = $update['message'] ?? $update['channel_post'] ?? null;
        if (!is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chat = $message['chat'] ?? null;
        if (!is_array($chat) || !isset($chat['id'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) $chat['id'];
        $text = isset($message['text']) ? trim((string) $message['text']) : '';
        if ($text === '') {
            return response()->json(['ok' => true]);
        }

        // Built-in commands
        if (str_starts_with($text, '/start') || str_starts_with($text, '/help')) {
            $telegram->sendMessage($chatId, implode("\n", [
                'Notion Mini bot ✅',
                "Your chat_id: {$chatId}",
                '',
                'Link your account:',
                '- Open Dashboard → Telegram → Save target',
                '- type=private, chat_id=<your chat_id>',
                '',
                'Then you can send natural commands like:',
                '- "Bugun 14:00 uchrashuv, 15:00 gacha, 10 min oldin eslat"',
                '- "Tanaka’dan config so‘ra"',
            ]));

            return response()->json(['ok' => true]);
        }

        // Resolve user by:
        // 1) users.telegram_chat_id
        // 2) telegram_targets.chat_id
        $user = User::query()->where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            $target = TelegramTarget::query()
                ->where('chat_id', $chatId)
                ->where('enabled', true)
                ->first();
            if ($target) {
                $user = User::query()->find($target->user_id);
            }
        }

        if (!$user) {
            $telegram->sendMessage($chatId, implode("\n", [
                "I can't find your account for chat_id={$chatId}.",
                'Please link it in Dashboard → Telegram → Save target (type=private).',
                '',
                'After linking, send your command again.',
            ]));

            return response()->json(['ok' => true]);
        }

        try {
            $plan = $ai->parseNaturalCommand($text);
            $action = $plan['action'] ?? null;

            if (!is_array($action)) {
                throw new \RuntimeException('AI plan is missing action');
            }

            $method = strtoupper(trim((string)($action['method'] ?? 'POST')));
            $path = trim((string)($action['path'] ?? ''));
            $body = $action['body'] ?? [];
            if (!is_array($body)) {
                $body = [];
            }

            // Safety: only allow POST create actions (no delete/update/search yet)
            if ($method !== 'POST' || !in_array($path, ['/api/tasks', '/api/calendar-events', '/api/notes'], true)) {
                $telegram->sendMessage($chatId, implode("\n", [
                    'Sorry, I cannot execute this action yet.',
                    "intent: ".((string)($plan['intent'] ?? 'unknown')),
                    "path: {$path}",
                    '',
                    'Try a create command for task/event/note.',
                ]));

                return response()->json(['ok' => true]);
            }

            if ($path === '/api/tasks') {
                $title = trim((string)($body['title'] ?? ''));
                if ($title === '') {
                    throw new \InvalidArgumentException('Task title is required');
                }

                $status = trim((string)($body['status'] ?? 'todo'));
                $status = match ($status) {
                    'todo', 'open' => 'open',
                    'doing' => 'doing',
                    'done', 'completed' => 'done',
                    default => $status,
                };

                $dueAt = null;
                if (isset($body['due_at']) && is_string($body['due_at']) && trim($body['due_at']) !== '') {
                    $dueAt = Carbon::parse($body['due_at']);
                }

                $task = Task::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'status' => $status,
                    'due_at' => $dueAt,
                    'source' => 'telegram.command',
                ]);

                $eventLogger->log($user, 'task.created', Task::class, $task->id, [
                    'title' => $task->title,
                    'status' => $task->status,
                    'due_at' => $task->due_at?->toIso8601String(),
                    'source' => $task->source,
                    '_origin_chat_id' => $chatId,
                ]);

                $telegram->sendMessage($chatId, "✅ Task created (#{$task->id}): {$task->title}");
            }

            if ($path === '/api/calendar-events') {
                $title = trim((string)($body['title'] ?? ''));
                $startAtRaw = isset($body['start_at']) ? (string)$body['start_at'] : '';
                if ($title === '' || trim($startAtRaw) === '') {
                    throw new \InvalidArgumentException('Event title and start_at are required');
                }

                $startAt = Carbon::parse($startAtRaw);
                $endAt = null;
                if (isset($body['end_at']) && is_string($body['end_at']) && trim($body['end_at']) !== '') {
                    $endAt = Carbon::parse($body['end_at']);
                }

                $remind = isset($body['remind_before_minute']) ? (int)$body['remind_before_minute'] : 10;
                $remind = max(0, min(10080, $remind));

                $event = CalendarEvent::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'remind_before_minute' => $remind,
                    'related_type' => isset($body['related_type']) ? (string)$body['related_type'] : null,
                    'related_id' => isset($body['related_id']) ? (int)$body['related_id'] : null,
                ]);

                $eventLogger->log($user, 'calendar_event.created', CalendarEvent::class, $event->id, [
                    'title' => $event->title,
                    'start_at' => $event->start_at->toIso8601String(),
                    'end_at' => $event->end_at?->toIso8601String(),
                    'remind_before_minute' => $event->remind_before_minute,
                    'related_type' => $event->related_type,
                    'related_id' => $event->related_id,
                    '_origin_chat_id' => $chatId,
                ]);

                $tz = (string)config('app.timezone', 'UTC');
                $telegram->sendMessage($chatId, "✅ Event created (#{$event->id}): {$event->title}\nstart: ".$event->start_at->setTimezone($tz)->format('Y-m-d H:i'));
            }

            if ($path === '/api/notes') {
                $title = trim((string)($body['title'] ?? ''));
                if ($title === '') {
                    throw new \InvalidArgumentException('Note title is required');
                }

                $tags = $body['tags'] ?? null;
                if (is_string($tags)) {
                    $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
                }
                if ($tags !== null && !is_array($tags)) {
                    $tags = null;
                }

                $note = Note::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'body' => isset($body['body']) ? (string)$body['body'] : null,
                    'tags' => $tags,
                ]);

                $eventLogger->log($user, 'note.created', Note::class, $note->id, [
                    'title' => $note->title,
                    'body' => $note->body,
                    'tags' => $note->tags,
                    '_origin_chat_id' => $chatId,
                ]);

                $telegram->sendMessage($chatId, "✅ Note created (#{$note->id}): {$note->title}");
            }
        } catch (\Throwable $e) {
            $telegram->sendMessage($chatId, "❌ Error: ".$e->getMessage());
        }

        return response()->json(['ok' => true]);
    }
}

