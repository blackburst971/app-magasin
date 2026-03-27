<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomReservation extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'title',
        'start_date',
        'end_date',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'admin_notes',
    ];

    protected $casts = [
        'start_date'  => 'datetime',
        'end_date'    => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'En attente',
            'approved'  => 'Approuvee',
            'rejected'  => 'Refusee',
            'cancelled' => 'Annulee',
            default     => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending'   => '#f59e0b',
            'approved'  => '#10b981',
            'rejected'  => '#ef4444',
            'cancelled' => '#6b7280',
            default     => '#6b7280',
        };
    }
}
