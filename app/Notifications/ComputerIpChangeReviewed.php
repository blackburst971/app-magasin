<?php

namespace App\Notifications;

use App\Models\ComputerIpChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComputerIpChangeReviewed extends Notification
{
    use Queueable;

    public $changeRequest;
    public $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(ComputerIpChangeRequest $changeRequest, string $status)
    {
        $this->changeRequest = $changeRequest;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $statusText = $this->status === 'approved' ? 'approuvée' : 'rejetée';

        return [
            'title' => 'Demande de changement d\'adresse IP ' . $statusText,
            'message' => 'Votre demande de changement d\'adresse IP pour le poste "' . $this->changeRequest->computer->name . '" a été ' . $statusText,
            'computer_id' => $this->changeRequest->computer_id,
            'computer_name' => $this->changeRequest->computer->name,
            'status' => $this->status,
            'link' => '/computers',
        ];
    }
}
