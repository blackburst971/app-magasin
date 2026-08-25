<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Preparation extends Model
{
    protected $fillable = [
        'prepared_by',
        'user_id',
        'title',
        'planned_start_at',
        'planned_end_at',
        'auto_checkout_at',
        'notes',
        'status',
        'reservation_id',
        'mode',
        'send_preparation_mail',
        'send_checkout_mail',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'planned_start_at'     => 'datetime',
        'planned_end_at'       => 'datetime',
        'auto_checkout_at'     => 'datetime',
        'validated_at'         => 'datetime',
        'send_preparation_mail'=> 'boolean',
        'send_checkout_mail'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function preparationAssets()
    {
        return $this->hasMany(PreparationAsset::class);
    }

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'preparation_assets')
            ->withPivot('previous_status', 'scanned_at')
            ->withTimestamps();
    }

    public function scopePreparing($query)
    {
        return $query->where('status', 'preparing');
    }
}
