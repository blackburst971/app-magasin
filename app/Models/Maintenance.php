<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $fillable = [
        'asset_id', 'created_by', 'title', 'description', 'provider',
        'cost', 'started_at', 'expected_return_at', 'resolved_at',
        'status', 'resolution_notes',
    ];

    protected $casts = [
        'started_at'          => 'date',
        'expected_return_at'  => 'date',
        'resolved_at'         => 'datetime',
        'cost'                => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'     => 'En attente',
            'in_progress' => 'En cours',
            'resolved'    => 'Résolu',
            default       => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending'     => 'yellow',
            'in_progress' => 'blue',
            'resolved'    => 'green',
            default       => 'gray',
        };
    }

    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }
}
