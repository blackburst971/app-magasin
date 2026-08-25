<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreparationAsset extends Model
{
    protected $fillable = [
        'preparation_id',
        'asset_id',
        'kit_id',
        'previous_status',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function preparation()
    {
        return $this->belongsTo(Preparation::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function kit()
    {
        return $this->belongsTo(\App\Models\Kit::class);
    }
}
