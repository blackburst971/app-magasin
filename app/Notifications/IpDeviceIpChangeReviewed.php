<?php

namespace App\Notifications;

use App\Models\IpDeviceIpChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IpDeviceIpChangeReviewed extends Notification
{
    use Queueable;

    public $changeRequest;
    public $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(IpDeviceIpChangeRequest $changeRequest, string $status)
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
            'message' => 'Votre demande de changement d\'adresse IP pour le périphérique IP "' . $this->changeRequest->ipDevice->name . '" a été ' . $statusText,
            'ip_device_id' => $this->changeRequest->ip_device_id,
            'ip_device_name' => $this->changeRequest->ipDevice->name,
            'status' => $this->status,
            'link' => '/ip-devices',
        ];
    }
}
