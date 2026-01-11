<?php

namespace Tests\Unit;

use App\Exceptions\AiException;
use App\Services\AIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    public function test_extract_tasks_returns_list(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');
        config()->set('services.openai.model', 'gpt-4o');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'tasks' => [
                            ['title' => 'Telegram logger sozlash', 'due' => '2026-01-12'],
                            ['title' => 'POC caching yakunlash', 'due' => null],
                        ],
                    ])]],
                ],
            ], 200),
        ]);

        $svc = new AIService();
        $out = $svc->extractTasks("...");

        $this->assertSame([
            ['title' => 'Telegram logger sozlash', 'due' => '2026-01-12'],
            ['title' => 'POC caching yakunlash'],
        ], $out);

        Http::assertSent(function ($req) {
            $data = $req->data();
            return $data['model'] === 'gpt-4o'
                && isset($data['messages'])
                && $req->hasHeader('Authorization');
        });
    }

    public function test_generate_title_and_tags_returns_object(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'title' => 'Frontend va Backend POC muhokamasi',
                        'tags' => ['frontend', 'backend', 'POC'],
                    ])]],
                ],
            ], 200),
        ]);

        $svc = new AIService();
        $out = $svc->generateTitleAndTags("...");

        $this->assertSame('Frontend va Backend POC muhokamasi', $out['title']);
        $this->assertSame(['frontend', 'backend', 'POC'], $out['tags']);
    }

    public function test_rate_limit_throws_ai_exception(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit'],
            ], 429, [
                'retry-after' => '12',
                'x-request-id' => 'req_123',
            ]),
        ]);

        $this->expectException(AiException::class);

        (new AIService())->extractTasks("...");
    }

    public function test_missing_key_throws_ai_exception(): void
    {
        config()->set('services.openai.api_key', '');

        $this->expectException(AiException::class);

        (new AIService())->generateTitleAndTags("...");
    }

    public function test_parse_natural_command_returns_intent_and_action(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');
        config()->set('services.openai.model', 'gpt-4o');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'intent' => 'create_task',
                        'confidence' => 0.86,
                        'action' => [
                            'method' => 'POST',
                            'path' => '/api/tasks',
                            'body' => [
                                'title' => 'Tanaka’dan config so‘rash',
                                'due_at' => null,
                                'status' => 'todo',
                                'source' => 'ai.command',
                            ],
                        ],
                        'explanation' => 'User task yaratmoqchi.',
                    ], JSON_UNESCAPED_UNICODE)]],
                ],
            ], 200),
        ]);

        $out = (new AIService())->parseNaturalCommand("Tanaka’dan config so‘ra");

        $this->assertSame('create_task', $out['intent']);
        $this->assertSame('/api/tasks', $out['action']['path']);
        $this->assertSame('Tanaka’dan config so‘rash', $out['action']['body']['title']);
    }

    public function test_classify_tone_returns_tone_and_urgency(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'tone' => 'stressed',
                        'sentiment' => -0.4,
                        'urgency' => 'high',
                        'language' => 'uz',
                    ])]],
                ],
            ], 200),
        ]);

        $out = (new AIService())->classifyTone("Juda shoshilinch, bugun tugatishim kerak!");

        $this->assertSame('stressed', $out['tone']);
        $this->assertSame('high', $out['urgency']);
        $this->assertSame('uz', $out['language']);
    }
}

