<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetReport extends Model
{
    protected $fillable = [
        'asset_id', 'reservation_id', 'user_id',
        'type', 'title', 'content',
        'is_validated', 'admin_notes',
        'validated_at', 'validated_by',
    ];

    protected $casts = [
        'is_validated' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function asset()        { return $this->belongsTo(Asset::class); }
    public function reservation()  { return $this->belongsTo(Reservation::class); }
    public function user()         { return $this->belongsTo(User::class); }
    public function validatedBy()  { return $this->belongsTo(User::class, 'validated_by'); }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'damage'      => '⚠️ Dégradation',
            'loss'        => '❌ Perte',
            'malfunction' => '🔧 Dysfonctionnement',
            'observation' => '📋 Observation',
            default       => $this->type,
        };
    }
}
