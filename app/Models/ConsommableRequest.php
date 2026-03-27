<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsommableRequest extends Model
{
    protected $table = 'consommable_requests';

    protected $fillable = [
        'user_id',
        'consommable_id',
        'quantity',
        'notes',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'approved_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consommable(): BelongsTo
    {
        return $this->belongsTo(Consommable::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Accessors ───────────────────────────────────────────────────────
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Refusée',
            default    => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default    => 'gray',
        };
    }

    // ─── Scopes ──────────────────────────────────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
