<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\ConsommableRequest;

class ConsommableRequestPendingNotification extends Notification
{
    use Queueable;

    public function __construct(public ConsommableRequest $consommableRequest) {}

    public function via($notifiable): array
    {
        if (\App\Models\Setting::get('alert_consommable_request_enabled', 'true') !== 'true') {
            return [];
        }
        $channel = \App\Models\Setting::get('alert_consommable_request_channel', 'notification');
        return match($channel) {
            'email'  => ['mail'],
            'both'   => ['database', 'mail'],
            default  => ['database'],
        };
    }

    public function toMail($notifiable): MailMessage
    {
        $consommable = $this->consommableRequest->consommable;
        $user        = $this->consommableRequest->user;

        return (new MailMessage)
            ->subject("📦 Demande de consommable — {$consommable->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("**{$user->name}** a effectué une demande de consommable.")
            ->line("**{$consommable->name}** — {$this->consommableRequest->quantity} {$consommable->unit}(s) demandé(s)")
            ->action('Voir les demandes', url(route('consommables.index')))
            ->line('Connectez-vous pour traiter cette demande.');
    }

    public function toArray($notifiable): array
    {
        $consommable = $this->consommableRequest->consommable;
        $user        = $this->consommableRequest->user;

        return [
            'type'                   => 'consommable_request_pending',
            'consommable_request_id' => $this->consommableRequest->id,
            'consommable_name'       => $consommable->name,
            'user_name'              => $user->name,
            'quantity'               => $this->consommableRequest->quantity,
            'unit'                   => $consommable->unit,
            'message'                => "Nouvelle demande de consommable de {$user->name} : {$this->consommableRequest->quantity} {$consommable->unit}(s) de « {$consommable->name} »",
        ];
    }
}
