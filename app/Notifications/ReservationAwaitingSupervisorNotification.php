<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Reservation;

class ReservationAwaitingSupervisorNotification extends Notification
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
            'type'           => 'awaiting_supervisor',
            'reservation_id' => $this->reservation->id,
            'title'          => $this->reservation->title,
            'user_name'      => $this->reservation->user->name,
            'assets_count'   => $this->reservation->assets()->count(),
            'message'        => "{$this->reservation->user->name} attend votre validation pour : {$this->reservation->title}",
        ];
    }
}
