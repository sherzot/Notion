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
}

