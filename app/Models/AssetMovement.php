<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'reservation_id',
        'user_id',
        'action',
        'notes',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'checkout' => 'Retrait',
            'return' => 'Retour',
            'maintenance' => 'Maintenance',
            'import' => 'Import',
            'status_change' => 'Changement statut',
            default => $this->action,
        };
    }
}
