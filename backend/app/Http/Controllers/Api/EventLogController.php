<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use Illuminate\Http\Request;

class EventLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = EventLog::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(30);

        return response()->json($logs);
    }
}

