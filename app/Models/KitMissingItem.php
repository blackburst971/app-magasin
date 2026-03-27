<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitMissingItem extends Model
{
    protected $fillable = [
        'kit_id',
        'asset_id',
        'reservation_id',
        'responsible_user_id',
        'notes',
        'regularized_at',
        'regularization_notes',
    ];

    protected $casts = [
        'regularized_at' => 'datetime',
    ];

    public function kit()
    {
        return $this->belongsTo(Kit::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function isRegularized(): bool
    {
        return $this->regularized_at !== null;
    }
}
