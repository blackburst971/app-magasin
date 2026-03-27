<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\ConsommableRequest;

class ConsommableRequestPendingNotification extends Notification
{
    use Queueable;

    public function __construct(public ConsommableRequest $consommableRequest) {}

    public function via($notifiable): array
    {
        return ['database'];
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
