<?php

namespace App\Notifications;

use App\Models\Computer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComputerIpChangeRequested extends Notification
{
    use Queueable;

    public $computer;
    public $requestedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Computer $computer, User $requestedBy)
    {
        $this->computer = $computer;
        $this->requestedBy = $requestedBy;
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
        return [
            'title' => 'Demande de changement d\'adresse IP',
            'message' => $this->requestedBy->name . ' a demandé un changement d\'adresse IP pour le poste "' . $this->computer->name . '"',
            'computer_id' => $this->computer->id,
            'computer_name' => $this->computer->name,
            'requested_by_id' => $this->requestedBy->id,
            'requested_by_name' => $this->requestedBy->name,
            'link' => '/computers/ip-change-requests',
        ];
    }
}
