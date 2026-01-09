<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\ReminderService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notion:send-reminders', function () {
    $sent = app(ReminderService::class)->sendDueReminders();
    $this->info("reminders_sent={$sent}");
})->purpose('Send Telegram reminders for upcoming calendar events');

Schedule::command('notion:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();
