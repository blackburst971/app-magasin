<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanSession extends Model
{
    protected $fillable = [
        'token',
        'user_id',
        'status',
        'expires_at',
        'scanned_items',
    ];

    protected $casts = [
        'expires_at'    => 'datetime',
        'scanned_items' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
