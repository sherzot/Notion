<?php

namespace Tests\Feature;

use App\Models\TelegramTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_command_replies_with_chat_id(): void
    {
        config()->set('services.telegram.token', 'test-bot');
        config()->set('services.telegram.api_base', 'https://api.telegram.org');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => [
                'chat' => ['id' => 424507309],
                'text' => '/start',
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        Http::assertSentCount(1);
        Http::assertSent(function ($req) {
            return str_contains((string) $req->url(), '/sendMessage')
                && ($req->data()['chat_id'] ?? null) === '424507309';
        });
    }

    public function test_webhook_creates_task_and_replies_once(): void
    {
        config()->set('services.telegram.token', 'test-bot');
        config()->set('services.telegram.api_base', 'https://api.telegram.org');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');
        config()->set('services.openai.model', 'gpt-4o');

        $user = User::factory()->create();
        TelegramTarget::create([
            'user_id' => $user->id,
            'type' => 'private',
            'chat_id' => '424507309',
            'enabled' => true,
        ]);

        Http::fake([
            // OpenAI parse-command
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'intent' => 'create_task',
                        'confidence' => 0.9,
                        'action' => [
                            'method' => 'POST',
                            'path' => '/api/tasks',
                            'body' => [
                                'title' => 'Tanaka’dan config so‘rash',
                                'due_at' => null,
                                'status' => 'todo',
                            ],
                        ],
                        'explanation' => 'Create a task.',
                    ], JSON_UNESCAPED_UNICODE)]],
                ],
            ], 200),
            // Telegram sendMessage (reply)
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 2,
            'message' => [
                'chat' => ['id' => 424507309],
                'text' => 'Tanaka’dan config so‘ra',
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        // We should reply once to the origin chat; event log notifier should skip origin.
        Http::assertSent(function ($req) {
            if (!str_contains((string) $req->url(), '/sendMessage')) {
                return true;
            }
            return ($req->data()['chat_id'] ?? null) === '424507309';
        });
    }
}

