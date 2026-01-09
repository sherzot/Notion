<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\EventLogger;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function index(Request $request)
    {
        $events = CalendarEvent::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('start_at')
            ->paginate(20);

        return response()->json($events);
    }

    public function store(Request $request, EventLogger $eventLogger)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'remind_before_minute' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'related_type' => ['nullable', 'string', 'max:255'],
            'related_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $event = CalendarEvent::create([
            ...$data,
            'user_id' => $request->user()->id,
            'remind_before_minute' => $data['remind_before_minute'] ?? 10,
        ]);

        $eventLogger->log(
            $request->user(),
            'calendar_event.created',
            CalendarEvent::class,
            $event->id,
            [
                'title' => $event->title,
                'start_at' => $event->start_at->toIso8601String(),
                'remind_before_minute' => $event->remind_before_minute,
            ]
        );

        return response()->json(['calendar_event' => $event], 201);
    }
}

