<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Services\EventLogger;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $notes = Note::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($notes);
    }

    public function store(Request $request, EventLogger $eventLogger)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $note = Note::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        $eventLogger->log(
            $request->user(),
            'note.created',
            Note::class,
            $note->id,
            ['title' => $note->title]
        );

        return response()->json(['note' => $note], 201);
    }
}

