<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignSession extends Model
{
    protected $fillable = [
        'token',
        'reservation_id',
        'supervisor_id',
        'purpose',
        'signature',
        'context',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'context'    => 'array',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}
