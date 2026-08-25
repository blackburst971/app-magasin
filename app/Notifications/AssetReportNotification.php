<?php

namespace App\Notifications;

use App\Models\AssetReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class AssetReportNotification extends Notification
{
    use Queueable;

    public function __construct(public AssetReport $report) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'       => "Nouveau rapport — {$this->report->asset->name}",
            'message'     => "{$this->report->user->name} a soumis un rapport : « {$this->report->title} »",
            'type_label'  => $this->report->getTypeLabel(),
            'asset_id'    => $this->report->asset_id,
            'report_id'   => $this->report->id,
            'url'         => route('assets.show', $this->report->asset_id) . '#reports',
        ];
    }
}
