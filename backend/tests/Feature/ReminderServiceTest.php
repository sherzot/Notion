<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramEventLogNotification;
use App\Models\CalendarEvent;
use App\Models\EventLog;
use App\Models\TelegramTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_command_creates_event_log_and_marks_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10T10:00:00Z'));

        Bus::fake();

        /** @var User $user */
        $user = User::factory()->create();

        TelegramTarget::create([
            'user_id' => $user->id,
            'type' => 'private',
            'chat_id' => '424507309',
            'enabled' => true,
        ]);

        $event = CalendarEvent::create([
            'user_id' => $user->id,
            'title' => 'Standup',
            'start_at' => Carbon::now()->addMinutes(10),
            'end_at' => Carbon::now()->addMinutes(25),
            'remind_before_minute' => 15, // due now
        ]);

        $this->artisan('notion:send-reminders')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->reminder_sent_at);

        $log = EventLog::query()
            ->where('user_id', $user->id)
            ->where('type', 'calendar_event.reminder')
            ->where('entity_type', CalendarEvent::class)
            ->where('entity_id', $event->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        Bus::assertDispatched(SendTelegramEventLogNotification::class);
    }

    public function test_reminder_command_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10T10:00:00Z'));

        Bus::fake();

        /** @var User $user */
        $user = User::factory()->create();

        TelegramTarget::create([
            'user_id' => $user->id,
            'type' => 'private',
            'chat_id' => '424507309',
            'enabled' => true,
        ]);

        $event = CalendarEvent::create([
            'user_id' => $user->id,
            'title' => 'Planning',
            'start_at' => Carbon::now()->addMinutes(5),
            'end_at' => null,
            'remind_before_minute' => 10, // due now
        ]);

        $this->artisan('notion:send-reminders')->assertExitCode(0);
        $this->artisan('notion:send-reminders')->assertExitCode(0);

        $count = EventLog::query()
            ->where('user_id', $user->id)
            ->where('type', 'calendar_event.reminder')
            ->where('entity_type', CalendarEvent::class)
            ->where('entity_id', $event->id)
            ->count();

        $this->assertSame(1, $count);
    }
}

