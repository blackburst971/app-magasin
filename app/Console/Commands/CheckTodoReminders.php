<?php

namespace App\Console\Commands;

use App\Models\TodoTask;
use App\Notifications\TodoTaskReminderNotification;
use Illuminate\Console\Command;

class CheckTodoReminders extends Command
{
    protected $signature   = 'app:check-todo-reminders';
    protected $description = 'Envoie les rappels de tâches To-Do dont la date de rappel est dépassée';

    public function handle(): void
    {
        $tasks = TodoTask::with(['assignedTo', 'creator', 'reservations'])
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->where('status', '!=', 'done')
            ->get();

        foreach ($tasks as $task) {
            // Notifier l'utilisateur assigné et/ou le créateur
            $userIds = collect();

            if ($task->assigned_to) {
                $userIds->push($task->assigned_to);
            }
            if ($task->created_by && $task->created_by !== $task->assigned_to) {
                $userIds->push($task->created_by);
            }

            $users = \App\Models\User::whereIn('id', $userIds->unique())->get();
            foreach ($users as $user) {
                $user->notify(new TodoTaskReminderNotification($task));
            }

            $task->update(['reminder_sent_at' => now()]);
        }

        $this->info("Rappels envoyés : {$tasks->count()}");
    }
}
