<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $fillable = [
        'asset_id',
        'assigned_to_asset_id',
        'assigned_by_user_id',
        'notes',
        'assigned_at',
        'returned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignedToAsset()
    {
        return $this->belongsTo(Asset::class, 'assigned_to_asset_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->returned_at === null;
    }
}
