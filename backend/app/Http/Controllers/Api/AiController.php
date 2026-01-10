<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function extractTasks(Request $request, AIService $ai)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:50000'],
        ]);

        $tasks = $ai->extractTasks($data['text']);

        return response()->json([
            'tasks' => $tasks,
        ]);
    }

    public function titleTags(Request $request, AIService $ai)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:50000'],
        ]);

        $out = $ai->generateTitleAndTags($data['text']);

        return response()->json($out);
    }
}

