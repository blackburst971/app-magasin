<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Preparation;

class PreparationReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public Preparation $preparation) {}

    public function via($notifiable): array
    {
        // Toujours envoyer une notification database
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $startDate = $this->preparation->planned_start_at?->format('d/m/Y H:i');
        $endDate = $this->preparation->planned_end_at?->format('d/m/Y H:i');

        return [
            'type'           => 'preparation_ready',
            'preparation_id' => $this->preparation->id,
            'reservation_id' => $this->preparation->reservation_id,
            'title'          => $this->preparation->title,
            'assets_count'   => $this->preparation->assets()->count(),
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'message'        => "Votre réservation « {$this->preparation->title} » est en préparation et prête à être retirée du {$startDate} au {$endDate}.",
        ];
    }
}
