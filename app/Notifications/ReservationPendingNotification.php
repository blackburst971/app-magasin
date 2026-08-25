<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Reservation;

class ReservationPendingNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function via($notifiable): array
    {
        if (\App\Models\Setting::get('notify_admin_new_reservation', 'true') !== 'true') {
            return [];
        }
        $channel = \App\Models\Setting::get('alert_new_reservation_channel', 'notification');
        return match($channel) {
            'email'  => ['mail'],
            'both'   => ['database', 'mail'],
            default  => ['database'],
        };
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("📋 Nouvelle réservation — {$this->reservation->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("**{$this->reservation->user->name}** a soumis une nouvelle demande de réservation.")
            ->line("**{$this->reservation->title}** — {$this->reservation->assets()->count()} équipement(s)")
            ->action('Voir la réservation', url('/reservations/' . $this->reservation->id))
            ->line('Connectez-vous pour approuver ou refuser cette réservation.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'reservation_pending',
            'reservation_id' => $this->reservation->id,
            'title'        => $this->reservation->title,
            'user_name'    => $this->reservation->user->name,
            'assets_count' => $this->reservation->assets()->count(),
            'message'      => "Nouvelle demande de réservation de {$this->reservation->user->name} : {$this->reservation->title}",
        ];
    }
}
