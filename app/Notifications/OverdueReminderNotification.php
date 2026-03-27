<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Reservation;

class OverdueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $assets = $this->reservation->assets->pluck('name')->join(', ');

        return (new MailMessage)
            ->subject('Rappel : matériel non retourné')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre réservation \"{$this->reservation->title}\" a dépassé la date de retour prévue.")
            ->line("Équipement(s) concerné(s) : {$assets}")
            ->line("Date de retour prévue : " . $this->reservation->end_date->format('d/m/Y'))
            ->action('Voir la réservation', url('/reservations/' . $this->reservation->id))
            ->line('Merci de retourner le matériel dès que possible.');
    }
}
