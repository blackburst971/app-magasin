<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Reservation;

class ReturnReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public int $minutesBeforeReturn,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray($notifiable): array
    {
        $timeLabel = $this->minutesBeforeReturn >= 60
            ? round($this->minutesBeforeReturn / 60, 1) . 'h'
            : $this->minutesBeforeReturn . ' min';

        return [
            'type'           => 'return_reminder',
            'reservation_id' => $this->reservation->id,
            'title'          => $this->reservation->title,
            'user_name'      => $this->reservation->user->name,
            'end_date'       => $this->reservation->end_date->format('d/m/Y H:i'),
            'assets_count'   => $this->reservation->assets()->count(),
            'message'        => "Retour prévu dans {$timeLabel} — {$this->reservation->title} ({$this->reservation->user->name})",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $timeLabel = $this->minutesBeforeReturn >= 60
            ? round($this->minutesBeforeReturn / 60, 1) . 'h'
            : $this->minutesBeforeReturn . ' min';

        $assets = $this->reservation->assets->pluck('name')->join(', ');

        return (new MailMessage)
            ->subject("⏰ Rappel retour dans {$timeLabel} — {$this->reservation->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Un retour d'équipement est prévu dans **{$timeLabel}**.")
            ->line("**Réservation :** {$this->reservation->title}")
            ->line("**Utilisateur :** {$this->reservation->user->name}")
            ->line("**Équipement(s) :** {$assets}")
            ->line("**Date de retour prévue :** " . $this->reservation->end_date->format('d/m/Y à H:i'))
            ->action('Voir la réservation', url('/reservations/' . $this->reservation->id))
            ->line('Pensez à préparer la réception du matériel.');
    }
}
