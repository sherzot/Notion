<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_tasks_endpoint_returns_tasks(): void
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

        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/extract-tasks', [
                'text' => 'Some note text',
            ]);

        $res->assertOk()->assertJson([
            'tasks' => [
                ['title' => 'Telegram logger sozlash', 'due' => '2026-01-12'],
                ['title' => 'POC caching yakunlash'],
            ],
        ]);
    }

    public function test_title_tags_endpoint_returns_title_and_tags(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');

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

        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/title-tags', [
                'text' => 'Some note text',
            ]);

        $res->assertOk()->assertJson([
            'title' => 'Frontend va Backend POC muhokamasi',
            'tags' => ['frontend', 'backend', 'POC'],
        ]);
    }

    public function test_rate_limit_returns_429_json(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit'],
            ], 429, [
                'retry-after' => '5',
                'x-request-id' => 'req_abc',
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/extract-tasks', [
                'text' => 'Some note text',
            ]);

        $res->assertStatus(429)->assertJson([
            'ok' => false,
            'error' => [
                'status' => 429,
                'request_id' => 'req_abc',
                'retry_after' => 5,
            ],
        ]);
    }

    public function test_validation_returns_422(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/extract-tasks', [])
            ->assertStatus(422);
    }
}

