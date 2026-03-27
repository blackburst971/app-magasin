<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Reservation;

class ReservationPendingNotification extends Notification
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
            'type' => 'reservation_pending',
            'reservation_id' => $this->reservation->id,
            'title' => $this->reservation->title,
            'user_name' => $this->reservation->user->name,
            'assets_count' => $this->reservation->assets()->count(),
            'message' => "Nouvelle demande de réservation de {$this->reservation->user->name} : {$this->reservation->title}",
        ];
    }
}
