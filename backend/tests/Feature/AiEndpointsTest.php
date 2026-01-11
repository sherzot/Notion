<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Note;
use App\Models\CalendarEvent;
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

    public function test_parse_command_endpoint_returns_action(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'intent' => 'create_calendar_event',
                        'confidence' => 0.9,
                        'action' => [
                            'method' => 'POST',
                            'path' => '/api/calendar-events',
                            'body' => [
                                'title' => 'Uchrashuv',
                                'start_at' => '2026-01-11T14:00:00+09:00',
                                'end_at' => '2026-01-11T15:00:00+09:00',
                                'remind_before_minute' => 10,
                            ],
                        ],
                        'explanation' => 'User event yaratmoqchi.',
                    ], JSON_UNESCAPED_UNICODE)]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/parse-command', [
                'text' => 'Bugun 14:00 da uchrashuv bor',
            ]);

        $res->assertOk()->assertJson([
            'intent' => 'create_calendar_event',
            'action' => [
                'path' => '/api/calendar-events',
            ],
        ]);
    }

    public function test_tone_endpoint_returns_tone(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'tone' => 'neutral',
                        'sentiment' => 0.1,
                        'urgency' => 'low',
                        'language' => 'uz',
                    ])]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/tone', ['text' => 'Bugun rejalarimni yozib qo‘ydim.'])
            ->assertOk()
            ->assertJson([
                'tone' => 'neutral',
                'urgency' => 'low',
                'language' => 'uz',
            ]);
    }

    public function test_weekly_digest_endpoint_returns_summary(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1/chat/completions');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'summary' => 'Yaxshi hafta: 3 ta task yaratdingiz, 1 tasi overdue.',
                        'highlights' => ['Tasklarni yozib borish odatini kuchaytirdingiz.'],
                        'risks' => ['Overdue task bor.'],
                        'suggestions' => ['Overdue taskni bugun 25 daqiqada yopib qo‘ying.'],
                        'stats' => [
                            'tasks_total' => 3,
                            'tasks_created_last_7d' => 3,
                            'tasks_overdue_now' => 1,
                            'notes_created_last_7d' => 1,
                            'events_upcoming_next_7d' => 1,
                        ],
                    ], JSON_UNESCAPED_UNICODE)]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        // Minimal data so context builder runs.
        Task::create([
            'user_id' => $user->id,
            'title' => 'Overdue task',
            'status' => 'todo',
            'due_at' => now()->subDay(),
        ]);
        Task::create([
            'user_id' => $user->id,
            'title' => 'New task',
            'status' => 'todo',
        ]);
        Task::create([
            'user_id' => $user->id,
            'title' => 'Doing task',
            'status' => 'doing',
        ]);
        Note::create([
            'user_id' => $user->id,
            'title' => 'Note',
            'body' => 'Meeting notes',
            'tags' => ['meeting'],
        ]);
        CalendarEvent::create([
            'user_id' => $user->id,
            'title' => 'Event',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'remind_before_minute' => 10,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/weekly-digest', [])
            ->assertOk()
            ->assertJsonStructure(['summary', 'highlights', 'risks', 'suggestions', 'stats']);
    }
}

