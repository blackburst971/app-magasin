<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReport extends Model
{
    protected $fillable = [
        'session_date',
        'label',
        'model_names',
        'total_count',
        'present_count',
        'missing_count',
        'pending_count',
        'notes',
        'created_by',
        'is_archived',
        'previous_statuses',
        'is_draft',
    ];

    protected $casts = [
        'session_date'       => 'date',
        'model_names'        => 'array',
        'is_archived'        => 'boolean',
        'previous_statuses'  => 'array',
        'is_draft'           => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReportItem::class);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_count === 0) return 0;
        return (int) round(($this->present_count + $this->missing_count) / $this->total_count * 100);
    }
}
