<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_tasks', function (Blueprint $table) {
            // Rappels Google Calendar : [{"method":"popup","minutes":30}, ...]
            $table->text('calendar_reminders')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('todo_tasks', function (Blueprint $table) {
            $table->dropColumn('calendar_reminders');
        });
    }
};
