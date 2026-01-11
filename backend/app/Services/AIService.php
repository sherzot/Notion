<?php

namespace App\Services;

use App\Exceptions\AiException;
use App\Models\CalendarEvent;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AIService
{
    public function extractTasks(string $text): array
    {
        $payload = $this->chatJson(
            system: $this->systemJsonOnly(),
            user: $this->promptExtractTasks($text),
            maxTokens: 600,
            temperature: 0.2,
        );

        $tasks = $payload['tasks'] ?? null;
        if (!is_array($tasks)) {
            throw new AiException('Invalid AI response: missing tasks');
        }

        $out = [];
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $title = isset($task['title']) ? trim((string) $task['title']) : '';
            if ($title === '') {
                continue;
            }

            $item = ['title' => $title];

            $due = $task['due'] ?? null;
            if (is_string($due) && trim($due) !== '') {
                // Expect YYYY-MM-DD; keep as-is if model already complies.
                $item['due'] = trim($due);
            }

            $out[] = $item;
        }

        return $out;
    }

    public function generateTitleAndTags(string $text): array
    {
        $payload = $this->chatJson(
            system: $this->systemJsonOnly(),
            user: $this->promptTitleAndTags($text),
            maxTokens: 250,
            temperature: 0.3,
        );

        $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
        $tags = $payload['tags'] ?? [];

        if ($title === '' || !is_array($tags)) {
            throw new AiException('Invalid AI response: expected title and tags');
        }

        $cleanTags = [];
        foreach ($tags as $tag) {
            $t = trim((string) $tag);
            if ($t === '') {
                continue;
            }
            $cleanTags[] = $t;
        }

        return [
            'title' => $title,
            'tags' => array_values(array_unique($cleanTags)),
        ];
    }

    /**
     * Natural-language command parser.
     *
     * Goal: return an "intent" + a ready-to-call API action (path + body).
     * This lets the frontend (or Telegram bot) translate user text into CRUD calls.
     */
    public function parseNaturalCommand(string $text): array
    {
        $payload = $this->chatJson(
            system: $this->systemJsonOnly(),
            user: $this->promptParseNaturalCommand($text),
            maxTokens: 450,
            temperature: 0.1,
        );

        $intent = isset($payload['intent']) ? trim((string) $payload['intent']) : '';
        $confidence = $payload['confidence'] ?? null;
        $action = $payload['action'] ?? null;

        if ($intent === '' || !is_array($action)) {
            throw new AiException('Invalid AI response: expected intent and action');
        }

        $out = [
            'intent' => $intent,
            'confidence' => is_numeric($confidence) ? (float) $confidence : null,
            'action' => [
                'method' => isset($action['method']) ? strtoupper(trim((string) $action['method'])) : 'POST',
                'path' => isset($action['path']) ? trim((string) $action['path']) : '',
                'body' => isset($action['body']) && is_array($action['body']) ? $action['body'] : new \stdClass(),
            ],
            'explanation' => isset($payload['explanation']) ? trim((string) $payload['explanation']) : null,
        ];

        if ($out['action']['path'] === '') {
            throw new AiException('Invalid AI response: missing action.path');
        }

        return $out;
    }

    /**
     * Classify tone/sentiment/urgency to support coach mode & smarter notifications.
     */
    public function classifyTone(string $text): array
    {
        $payload = $this->chatJson(
            system: $this->systemJsonOnly(),
            user: $this->promptClassifyTone($text),
            maxTokens: 220,
            temperature: 0.0,
        );

        $tone = isset($payload['tone']) ? trim((string) $payload['tone']) : '';
        $sentiment = $payload['sentiment'] ?? null;
        $urgency = isset($payload['urgency']) ? trim((string) $payload['urgency']) : '';

        if ($tone === '' || $urgency === '') {
            throw new AiException('Invalid AI response: expected tone and urgency');
        }

        return [
            'tone' => $tone,
            'sentiment' => is_numeric($sentiment) ? (float) $sentiment : null,
            'urgency' => $urgency,
            'language' => isset($payload['language']) ? trim((string) $payload['language']) : null,
        ];
    }

    /**
     * Generate a weekly digest (coach mode) for a user.
     * This is intentionally on-demand for now (later: scheduled + saved to DB).
     */
    public function generateWeeklyDigest(int $userId): array
    {
        $user = User::query()->findOrFail($userId);

        $now = Carbon::now();
        $from = $now->copy()->subDays(7);

        $tasksTotal = Task::query()->where('user_id', $user->id)->count();
        $tasksLast7d = Task::query()->where('user_id', $user->id)->where('created_at', '>=', $from)->count();
        $tasksOverdue = Task::query()
            ->where('user_id', $user->id)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereNotIn('status', ['done', 'completed'])
            ->count();

        $tasksByStatus = Task::query()
            ->selectRaw('status, COUNT(*) as c')
            ->where('user_id', $user->id)
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $notesLast7d = Note::query()->where('user_id', $user->id)->where('created_at', '>=', $from)->count();
        $eventsUpcoming7d = CalendarEvent::query()
            ->where('user_id', $user->id)
            ->where('start_at', '>=', $now)
            ->where('start_at', '<=', $now->copy()->addDays(7))
            ->count();

        $context = [
            'timezone' => (string) config('app.timezone'),
            'range' => [
                'from' => $from->toIso8601String(),
                'to' => $now->toIso8601String(),
            ],
            'tasks' => [
                'total' => $tasksTotal,
                'created_last_7d' => $tasksLast7d,
                'overdue_now' => $tasksOverdue,
                'by_status' => $tasksByStatus,
            ],
            'notes' => [
                'created_last_7d' => $notesLast7d,
            ],
            'calendar' => [
                'upcoming_next_7d' => $eventsUpcoming7d,
            ],
        ];

        $payload = $this->chatJson(
            system: $this->systemJsonOnly(),
            user: $this->promptWeeklyDigest($context),
            maxTokens: 650,
            temperature: 0.4,
        );

        // Very lightweight validation; keep it flexible for iteration.
        if (!isset($payload['summary'])) {
            throw new AiException('Invalid AI response: expected summary');
        }

        return $payload;
    }

    /**
     * Alias for "coach analysis" (we can split later if you want two different outputs).
     */
    public function analyzeProductivity(int $userId): array
    {
        return $this->generateWeeklyDigest($userId);
    }

    private function chatJson(string $system, string $user, int $maxTokens, float $temperature): array
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            throw new AiException('OPENAI_API_KEY is not configured');
        }

        $baseUrl = $this->normalizeBaseUrl((string) config('services.openai.base_url', 'https://api.openai.com/v1/chat/completions'));
        $model = (string) config('services.openai.model', 'gpt-4o');
        $timeout = (int) config('services.openai.timeout', 30);

        $res = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->retry(2, 250, throw: false)
            ->post($baseUrl, [
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        return $this->parseChatCompletionsJson($res);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $u = trim($baseUrl);
        if ($u === '') {
            return 'https://api.openai.com/v1/chat/completions';
        }

        // Allow both:
        // - https://api.openai.com/v1/chat/completions   (full endpoint)
        // - https://api.openai.com/v1                   (API root)
        $u = rtrim($u, '/');
        if (Str::endsWith($u, '/v1')) {
            return $u.'/chat/completions';
        }

        return $u;
    }

    private function parseChatCompletionsJson(Response $res): array
    {
        if ($res->status() === 429) {
            throw new AiException(
                message: 'OpenAI rate limit exceeded',
                statusCode: 429,
                requestId: $res->header('x-request-id'),
                retryAfterSeconds: $this->parseRetryAfter($res->header('retry-after')),
            );
        }

        if ($res->failed()) {
            $msg = 'OpenAI request failed';
            $body = $res->json();
            if (is_array($body) && isset($body['error']['message'])) {
                $msg = (string) $body['error']['message'];
            }
            throw new AiException(
                message: $msg,
                statusCode: $res->status(),
                requestId: $res->header('x-request-id'),
            );
        }

        $content = data_get($res->json(), 'choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            throw new AiException('Invalid AI response: empty content');
        }

        return $this->decodeJsonLoose($content);
    }

    private function decodeJsonLoose(string $content): array
    {
        $raw = trim($content);

        // Strip ```json fences if model returns them.
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
        $raw = trim($raw);

        // If it contains extra text, try to cut to the first {...} block.
        $first = strpos($raw, '{');
        $last = strrpos($raw, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $raw = substr($raw, $first, $last - $first + 1);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new AiException('Invalid AI response: not valid JSON');
        }

        return $decoded;
    }

    private function parseRetryAfter(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        if (ctype_digit($v)) {
            return (int) $v;
        }
        return null;
    }

    private function systemJsonOnly(): string
    {
        return 'Return ONLY valid JSON. No markdown, no code fences, no extra text.';
    }

    private function promptExtractTasks(string $text): string
    {
        $t = $this->limitText($text);

        return implode("\n", [
            'Extract actionable tasks from the text. If no tasks, return an empty list.',
            'Output schema:',
            '{ "tasks": [ { "title": "string", "due": "YYYY-MM-DD | null" } ] }',
            'Rules:',
            '- Keep titles short and imperative.',
            '- If due date is not explicitly present, use null.',
            '- Do NOT hallucinate dates.',
            '',
            'TEXT:',
            $t,
        ]);
    }

    private function promptTitleAndTags(string $text): string
    {
        $t = $this->limitText($text);

        return implode("\n", [
            'Generate a short title and up to 5 tags for the note.',
            'Output schema:',
            '{ "title": "string", "tags": ["string"] }',
            'Rules:',
            '- Tags should be concise keywords (no #).',
            '- Keep the title under ~60 characters.',
            '',
            'TEXT:',
            $t,
        ]);
    }

    private function promptParseNaturalCommand(string $text): string
    {
        $t = $this->limitText($text);
        $tz = (string) config('app.timezone');

        return implode("\n", [
            'You are a command parser for a productivity app (notes, tasks, calendar).',
            'Interpret the user text and produce ONE best intent + a ready-to-call API action.',
            'Only use these intents:',
            '- create_task',
            '- create_calendar_event',
            '- create_note',
            '- search',
            '- unknown',
            '',
            'Output schema:',
            '{',
            '  "intent": "string",',
            '  "confidence": 0.0,',
            '  "action": {',
            '    "method": "POST",',
            '    "path": "/api/tasks | /api/calendar-events | /api/notes | /api/search",',
            '    "body": { ... }',
            '  },',
            '  "explanation": "string"',
            '}',
            '',
            'Rules:',
            '- If a task is requested, use path "/api/tasks" with body keys: title, due_at(optional ISO8601), status(optional: todo|doing|done), source="ai.command".',
            '- If a calendar event is requested, use path "/api/calendar-events" with body keys: title, start_at(ISO8601), end_at(optional ISO8601), remind_before_minute(optional int), related_type(optional), related_id(optional).',
            '- If user says "today/tomorrow/at 14:00", resolve it using timezone '.$tz.' and output ISO8601.',
            '- Do not invent dates/times. If unclear, pick intent unknown and explain what is missing.',
            '- The API paths must be exact.',
            '',
            'USER TEXT:',
            $t,
        ]);
    }

    private function promptClassifyTone(string $text): string
    {
        $t = $this->limitText($text);

        return implode("\n", [
            'Classify the message tone and urgency.',
            'Output schema:',
            '{ "tone": "neutral|formal|casual|friendly|angry|stressed", "sentiment": -1.0, "urgency": "low|medium|high", "language": "string" }',
            'Rules:',
            '- sentiment: -1 (very negative) to +1 (very positive). Use null if unsure.',
            '- Keep it conservative; do not over-interpret.',
            '',
            'TEXT:',
            $t,
        ]);
    }

    private function promptWeeklyDigest(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return implode("\n", [
            'You are a productivity coach. Create a weekly digest from the provided stats.',
            'Output schema:',
            '{',
            '  "summary": "string",',
            '  "highlights": ["string"],',
            '  "risks": ["string"],',
            '  "suggestions": ["string"],',
            '  "stats": { "tasks_total": 0, "tasks_created_last_7d": 0, "tasks_overdue_now": 0, "notes_created_last_7d": 0, "events_upcoming_next_7d": 0 }',
            '}',
            'Rules:',
            '- Keep it short and actionable.',
            '- If overdue_now > 0, include at least one suggestion about it.',
            '',
            'CONTEXT_JSON:',
            $json ?: '{}',
        ]);
    }

    private function limitText(string $text): string
    {
        $max = (int) config('services.openai.max_input_chars', 12000);
        $max = max(500, $max);

        $t = Str::of($text)->trim()->toString();
        if (mb_strlen($t) <= $max) {
            return $t;
        }

        return mb_substr($t, 0, $max);
    }
}

