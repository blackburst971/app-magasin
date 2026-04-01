<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Champs rappel sur les tâches
        Schema::table('todo_tasks', function (Blueprint $table) {
            $table->dateTime('reminder_at')->nullable()->after('due_date');
            $table->string('reminder_channel', 20)->nullable()->after('reminder_at'); // notification|email|both
            $table->dateTime('reminder_sent_at')->nullable()->after('reminder_channel');
        });

        // Table pivot tâches ↔ réservations
        Schema::create('todo_task_reservations', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('todo_tasks')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->primary(['task_id', 'reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_task_reservations');
        Schema::table('todo_tasks', function (Blueprint $table) {
            $table->dropColumn(['reminder_at', 'reminder_channel', 'reminder_sent_at']);
        });
    }
};
