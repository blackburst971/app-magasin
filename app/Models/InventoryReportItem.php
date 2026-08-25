<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReportItem extends Model
{
    protected $fillable = [
        'inventory_report_id',
        'asset_id',
        'asset_tag',
        'asset_name',
        'model_name',
        'location',
        'serial',
        'scan_status',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(InventoryReport::class, 'inventory_report_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
