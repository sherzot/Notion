<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\EventLogController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TelegramTargetController;
use App\Http\Controllers\Api\TelegramTestController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/event-logs', [EventLogController::class, 'index']);

    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);

    Route::get('/calendar-events', [CalendarEventController::class, 'index']);
    Route::post('/calendar-events', [CalendarEventController::class, 'store']);

    Route::get('/telegram-targets', [TelegramTargetController::class, 'index']);
    Route::post('/telegram-targets', [TelegramTargetController::class, 'store']);
    Route::patch('/telegram-targets/{telegramTarget}', [TelegramTargetController::class, 'update']);
    Route::delete('/telegram-targets/{telegramTarget}', [TelegramTargetController::class, 'destroy']);

    Route::post('/telegram/test', TelegramTestController::class);
});

Route::post('/telegram/webhook', TelegramWebhookController::class);


