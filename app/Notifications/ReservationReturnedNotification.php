<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Reservation;

class ReservationReturnedNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'           => 'reservation_returned',
            'reservation_id' => $this->reservation->id,
            'title'          => $this->reservation->title,
            'assets_count'   => $this->reservation->assets()->count(),
            'message'        => "Le retour de votre réservation « {$this->reservation->title} » a été confirmé. Merci !",
        ];
    }
}
