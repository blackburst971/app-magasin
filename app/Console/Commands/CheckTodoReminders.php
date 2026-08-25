<?php

namespace App\Console\Commands;

use App\Models\TodoTaskReminder;
use App\Notifications\TodoTaskReminderNotification;
use Illuminate\Console\Command;

class CheckTodoReminders extends Command
{
    protected $signature   = 'app:check-todo-reminders';
    protected $description = 'Envoie les rappels de tâches To-Do dont la date de rappel est dépassée';

    public function handle(): void
    {
        $reminders = TodoTaskReminder::with(['task' => fn($q) => $q->with(['assignedTo', 'creator', 'reservations'])])
            ->where('reminder_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->whereHas('task', fn($q) => $q->where('status', '!=', 'done'))
            ->get();

        if ($reminders->isEmpty()) {
            $this->info("Aucun rappel à envoyer.");
            return;
        }

        foreach ($reminders as $reminder) {
            $task = $reminder->task;

            // Récupérer les utilisateurs à notifier
            $userIds = collect();
            if ($task->assigned_to) {
                $userIds->push($task->assigned_to);
            }
            if ($task->created_by && $task->created_by !== $task->assigned_to) {
                $userIds->push($task->created_by);
            }

            $users = \App\Models\User::whereIn('id', $userIds->unique())->get();

            // Envoyer les notifications
            foreach ($users as $user) {
                $user->notify(new TodoTaskReminderNotification($task, $reminder));
            }

            // IMPORTANT : Marquer comme envoyé IMMÉDIATEMENT après l'envoi
            // pour éviter les doublons lors de la prochaine exécution
            $reminder->reminder_sent_at = now();
            $reminder->save();

            $this->info("Rappel envoyé pour la tâche #{$task->id} « {$task->title} » à {$users->count()} utilisateur(s)");
        }

        $this->info("Total : {$reminders->count()} rappel(s) envoyé(s)");
    }
}
