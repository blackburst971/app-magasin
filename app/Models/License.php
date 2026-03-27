<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class License extends Model
{
    protected $fillable = [
        'name', 'version', 'manufacturer', 'license_key', 'seats',
        'type', 'is_cloud', 'purchase_date', 'expiry_date',
        'purchase_cost', 'order_number', 'supplier_id', 'notes',
    ];

    protected $casts = [
        'is_cloud'      => 'boolean',
        'purchase_date' => 'date',
        'expiry_date'   => 'date',
        'purchase_cost' => 'decimal:2',
        'seats'         => 'integer',
    ];

    /* ── Relations ─────────────────────────────────────────────────────── */

    public function assignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function computers(): BelongsToMany
    {
        return $this->belongsToMany(Computer::class, 'license_assignments')
            ->withPivot(['id', 'notes', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /* ── Accesseurs ─────────────────────────────────────────────────────── */

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date !== null
            && ! $this->is_expired
            && $this->expiry_date->diffInDays(now()) <= 30;
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'per_seat'    => 'Par poste',
            'volume'      => 'Volume',
            'oem'         => 'OEM',
            'cloud'       => 'Cloud / SaaS',
            'dongle'      => 'Dongle',
            'open_source' => 'Open Source',
            default       => $this->type,
        };
    }
}
