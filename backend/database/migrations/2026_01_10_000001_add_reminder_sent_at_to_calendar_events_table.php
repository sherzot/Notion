<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->timestampTz('reminder_sent_at')->nullable()->after('remind_before_minute');
            $table->index(['reminder_sent_at', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['reminder_sent_at', 'start_at']);
            $table->dropColumn('reminder_sent_at');
        });
    }
};

