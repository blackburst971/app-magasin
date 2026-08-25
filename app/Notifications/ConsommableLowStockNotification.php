<?php

namespace App\Notifications;

use App\Models\Consommable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ConsommableLowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Consommable $consommable
    ) {}

    public function via($notifiable): array
    {
        $channel = \App\Models\Setting::get('alert_low_stock_channel', 'both');
        return match($channel) {
            'notification' => ['database'],
            'email'        => ['mail'],
            default        => ['database', 'mail'],
        };
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚠️ Stock bas — {$this->consommable->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le stock du consommable **{$this->consommable->name}** est bas.")
            ->line("Stock actuel : **{$this->consommable->quantity} {$this->consommable->unit}(s)** (seuil minimum : {$this->consommable->min_stock})")
            ->action('Gérer le stock', url(route('consommables.index')))
            ->line('Pensez à réapprovisionner ce consommable.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'              => 'consommable_low_stock',
            'consommable_id'    => $this->consommable->id,
            'consommable_name'  => $this->consommable->name,
            'quantity'          => $this->consommable->quantity,
            'min_stock'         => $this->consommable->min_stock,
            'unit'              => $this->consommable->unit,
            'message'           => "Stock bas : {$this->consommable->name} — {$this->consommable->quantity} {$this->consommable->unit}(s) restant(s) (seuil : {$this->consommable->min_stock})",
        ];
    }
}
