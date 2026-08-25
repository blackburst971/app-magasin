<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetPlaceAssignment extends Model
{
    protected $fillable = [
        'asset_id',
        'place_id',
        'assigned_by',
        'assigned_at',
        'return_expected_at',
        'returned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at'       => 'datetime',
        'return_expected_at' => 'datetime',
        'returned_at'       => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isPermanent(): bool
    {
        return $this->return_expected_at === null;
    }

    public function isActive(): bool
    {
        return $this->returned_at === null;
    }

    public function isOverdue(): bool
    {
        return $this->return_expected_at !== null
            && $this->returned_at === null
            && $this->return_expected_at->isPast();
    }
}
