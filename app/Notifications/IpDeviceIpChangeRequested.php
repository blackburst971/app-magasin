<?php

namespace App\Notifications;

use App\Models\IpDevice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IpDeviceIpChangeRequested extends Notification
{
    use Queueable;

    public $ipDevice;
    public $requestedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(IpDevice $ipDevice, User $requestedBy)
    {
        $this->ipDevice = $ipDevice;
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
            'message' => $this->requestedBy->name . ' a demandé un changement d\'adresse IP pour le périphérique IP "' . $this->ipDevice->name . '"',
            'ip_device_id' => $this->ipDevice->id,
            'ip_device_name' => $this->ipDevice->name,
            'requested_by_id' => $this->requestedBy->id,
            'requested_by_name' => $this->requestedBy->name,
            'link' => '/ip-devices/ip-change-requests',
        ];
    }
}
