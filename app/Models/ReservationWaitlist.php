<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationWaitlist extends Model
{
    protected $fillable = [
        'asset_id', 'user_id', 'desired_start', 'desired_end', 'notes', 'notified_at',
    ];

    protected $casts = [
        'desired_start' => 'date',
        'desired_end'   => 'date',
        'notified_at'   => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
