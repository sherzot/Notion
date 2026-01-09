<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\EventLogger;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($tasks);
    }

    public function store(Request $request, EventLogger $eventLogger)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'due_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:2048'],
        ]);

        $task = Task::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => $data['status'] ?? 'open',
        ]);

        $eventLogger->log(
            $request->user(),
            'task.created',
            Task::class,
            $task->id,
            [
                'title' => $task->title,
                'status' => $task->status,
                'due_at' => $task->due_at?->toIso8601String(),
                'source' => $task->source,
                'link' => $task->link,
            ]
        );

        return response()->json(['task' => $task], 201);
    }
}

