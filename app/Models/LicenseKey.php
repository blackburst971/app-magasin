<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'key_code',
        'status',
        'assigned_to_computer_id',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /* ── Relations ─────────────────────────────────────────────────────── */

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function assignedToComputer(): BelongsTo
    {
        return $this->belongsTo(Computer::class, 'assigned_to_computer_id');
    }

    /* ── Méthodes ─────────────────────────────────────────────────────── */

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'assigned';
    }
}
