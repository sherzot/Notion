<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramEventLogNotification;
use App\Models\EventLog;
use App\Models\TelegramTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_log_dispatches_telegram_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        EventLog::create([
            'user_id' => $user->id,
            'type' => 'task.created',
            'entity_type' => 'Task',
            'entity_id' => 1,
            'payload_json' => ['title' => 'X'],
        ]);

        Queue::assertPushed(SendTelegramEventLogNotification::class);
    }

    public function test_job_sends_telegram_message_to_enabled_targets(): void
    {
        config()->set('services.telegram.token', 'TEST_TOKEN');
        config()->set('services.telegram.api_base', 'https://api.telegram.org');

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        TelegramTarget::create([
            'user_id' => $user->id,
            'type' => 'private',
            'chat_id' => '123',
            'enabled' => true,
        ]);

        $log = EventLog::create([
            'user_id' => $user->id,
            'type' => 'task.created',
            'entity_type' => 'Task',
            'entity_id' => 1,
            'payload_json' => ['title' => 'Hello'],
        ]);

        (new SendTelegramEventLogNotification($log->id))->handle(app()->make(\App\Services\TelegramNotifierService::class));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/botTEST_TOKEN/sendMessage')
                && $request['chat_id'] === '123'
                && str_contains($request['text'], 'task.created');
        });
    }
}

