<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kind' => ['nullable', 'in:task,note,calendar,reminder'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = EventLog::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        $kind = $data['kind'] ?? null;
        if ($kind) {
            match ($kind) {
                'task' => $logs->where('type', 'like', 'task.%'),
                'note' => $logs->where('type', 'like', 'note.%'),
                'calendar' => $logs->where('type', 'like', 'calendar_event.%')->where('type', '!=', 'calendar_event.reminder'),
                'reminder' => $logs->where('type', 'like', '%.reminder'),
                default => null,
            };
        }

        $q = trim((string) ($data['q'] ?? ''));
        if ($q !== '') {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $logs->where(function ($qq) use ($like) {
                    $qq->where('type', 'ilike', $like)
                        ->orWhereRaw("payload_json::text ilike ?", [$like]);
                });
            } else {
                $like = '%' . $q . '%';
                $logs->where(function ($qq) use ($like) {
                    $qq->where('type', 'like', $like)
                        ->orWhere('payload_json', 'like', $like);
                });
            }
        }

        $logs = $logs->paginate(30)->appends($request->query());

        return response()->json($logs);
    }
}

