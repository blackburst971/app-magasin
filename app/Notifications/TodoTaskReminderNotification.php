<?php

namespace App\Notifications;

use App\Models\TodoTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TodoTaskReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public TodoTask $task) {}

    public function via($notifiable): array
    {
        return match ($this->task->reminder_channel) {
            'email'        => ['mail'],
            'both'         => ['database', 'mail'],
            default        => ['database'],
        };
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'todo_reminder',
            'task_id'    => $this->task->id,
            'project_id' => $this->task->project_id,
            'title'      => $this->task->title,
            'message'    => "📋 Rappel de tâche : {$this->task->title}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $reservationLines = collect();
        foreach ($this->task->reservations as $res) {
            $reservationLines->push(
                "**{$res->title}** — du " . $res->start_date->format('d/m/Y') . ' au ' . $res->end_date->format('d/m/Y')
            );
        }

        $mail = (new MailMessage)
            ->subject("📋 Rappel de tâche : {$this->task->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Cette tâche est à démarrer :")
            ->line("**{$this->task->title}**");

        if ($this->task->description) {
            $mail->line($this->task->description);
        }

        if ($reservationLines->isNotEmpty()) {
            $mail->line('**Réservations liées :**');
            foreach ($reservationLines as $line) {
                $mail->line($line);
            }
        }

        return $mail
            ->action('Voir la tâche', url('/todo'))
            ->line('Bonne continuation !');
    }
}
