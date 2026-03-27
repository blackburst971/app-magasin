<?php

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistAssetAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(public Asset $asset) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("✅ {$this->asset->name} est maintenant disponible")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("L'équipement **{$this->asset->name}** ({$this->asset->asset_tag}) que vous avez mis en liste d'attente est maintenant disponible.")
            ->action('Faire une réservation', url(route('reservations.create', ['asset' => $this->asset->id])))
            ->line("Dépêchez-vous, d'autres utilisateurs pourraient également être notifiés.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'waitlist_available',
            'asset_id'   => $this->asset->id,
            'asset_name' => $this->asset->name,
            'asset_tag'  => $this->asset->asset_tag,
            'message'    => "{$this->asset->name} est maintenant disponible.",
        ];
    }
}
