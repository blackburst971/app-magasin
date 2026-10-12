<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Reservation;

class ReservationAwaitingSignatureNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function via($notifiable): array
    {
        // Uniquement notification database (discrète)
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'            => 'reservation_awaiting_signature',
            'reservation_id'  => $this->reservation->id,
            'title'           => $this->reservation->title,
            'user_name'       => $this->reservation->user->name,
            'supervisor_name' => $this->reservation->supervisorChosen
                ? $this->reservation->supervisorChosen->name
                : ($this->reservation->user->getSupervisor()?->name ?? 'Responsable'),
            'message'         => "Demande de {$this->reservation->user->name} en attente de signature du responsable pour « {$this->reservation->title} »",
            'signature_url'   => route('reservations.supervisor-sign'),
        ];
    }
}
