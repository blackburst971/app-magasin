<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_task_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_task_id')->constrained('todo_tasks')->cascadeOnDelete();
            $table->datetime('reminder_at');
            $table->string('reminder_channel', 20)->default('notification');
            $table->datetime('reminder_sent_at')->nullable();
            $table->timestamps();
        });

        // Migrer les rappels existants depuis todo_tasks
        DB::table('todo_tasks')
            ->whereNotNull('reminder_at')
            ->orderBy('id')
            ->each(function ($task) {
                DB::table('todo_task_reminders')->insert([
                    'todo_task_id'     => $task->id,
                    'reminder_at'      => $task->reminder_at,
                    'reminder_channel' => $task->reminder_channel ?? 'notification',
                    'reminder_sent_at' => $task->reminder_sent_at,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            });

        // Supprimer les anciennes colonnes de rappel sur todo_tasks
        Schema::table('todo_tasks', function (Blueprint $table) {
            $table->dropColumn(['reminder_at', 'reminder_channel', 'reminder_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('todo_tasks', function (Blueprint $table) {
            $table->datetime('reminder_at')->nullable()->after('due_date');
            $table->string('reminder_channel', 20)->nullable()->after('reminder_at');
            $table->datetime('reminder_sent_at')->nullable()->after('reminder_channel');
        });

        // Restituer uniquement le premier rappel par tâche
        DB::table('todo_task_reminders')
            ->orderBy('id')
            ->each(function ($r) {
                DB::table('todo_tasks')->where('id', $r->todo_task_id)->update([
                    'reminder_at'      => $r->reminder_at,
                    'reminder_channel' => $r->reminder_channel,
                    'reminder_sent_at' => $r->reminder_sent_at,
                ]);
            });

        Schema::dropIfExists('todo_task_reminders');
    }
};
