<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Reservation;

class ReservationReturnedNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function via($notifiable): array
    {
        if (\App\Models\Setting::get('alert_reservation_returned_enabled', 'true') !== 'true') {
            return [];
        }
        $channel = \App\Models\Setting::get('alert_reservation_returned_channel', 'notification');
        // L'email est toujours géré exclusivement par le Mailable ReservationReturned
        // dans le contrôleur (template HTML complet). Cette notification ne gère que
        // les notifications en base de données (cloche in-app).
        return match($channel) {
            'email'  => [],           // pas de cloche, l'email est envoyé par le contrôleur
            'both'   => ['database'], // cloche in-app, l'email est envoyé par le contrôleur
            default  => ['database'],
        };
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("✅ Retour confirmé — {$this->reservation->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le retour de votre réservation **{$this->reservation->title}** a été confirmé.")
            ->action('Voir la réservation', url('/reservations/' . $this->reservation->id))
            ->line('Merci pour votre confiance.');
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
