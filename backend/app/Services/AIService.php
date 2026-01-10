<?php

namespace App\Services;

use App\Exceptions\AiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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

