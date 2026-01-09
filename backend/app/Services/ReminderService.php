<?php

namespace App\Services;

use App\Models\CalendarEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReminderService
{
    public function __construct(
        private readonly EventLogger $eventLogger,
    ) {}

    /**
     * Find due calendar reminders and send them via Telegram (through EventLogger).
     * Returns number of reminders sent.
     */
    public function sendDueReminders(?Carbon $now = null): int
    {
        $now = $now ?: Carbon::now();

        $count = 0;

        // Row-level locking to avoid duplicates if multiple schedulers run.
        DB::transaction(function () use ($now, &$count) {
            $query = CalendarEvent::query()
                ->whereNull('reminder_sent_at')
                ->where('start_at', '>=', $now); // don't remind past events

            // PostgreSQL: do the reminder-due computation in SQL.
            // SQLite (tests): interval arithmetic isn't supported; filter in PHP instead.
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $query->whereRaw("(start_at - (remind_before_minute * interval '1 minute')) <= ?", [$now]);
            }

            $events = $query->lockForUpdate()->get();

            foreach ($events as $event) {
                if ($driver !== 'pgsql') {
                    $dueAt = $event->start_at->copy()->subMinutes((int) $event->remind_before_minute);
                    if ($now->lt($dueAt)) {
                        continue;
                    }
                }

                $this->eventLogger->log(
                    $event->user,
                    'calendar_event.reminder',
                    CalendarEvent::class,
                    $event->id,
                    [
                        'title' => $event->title,
                        'start_at' => $event->start_at->toIso8601String(),
                        'end_at' => $event->end_at?->toIso8601String(),
                        'remind_before_minute' => $event->remind_before_minute,
                        'related_type' => $event->related_type,
                        'related_id' => $event->related_id,
                    ]
                );

                $event->forceFill(['reminder_sent_at' => $now])->save();
                $count++;
            }
        }, 3);

        return $count;
    }
}

