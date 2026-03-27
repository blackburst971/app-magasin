<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationMessage extends Model
{
    protected $fillable = ['reservation_id', 'sender_id', 'body', 'read_by_admin', 'read_by_user'];

    protected $casts = [
        'read_by_admin' => 'boolean',
        'read_by_user'  => 'boolean',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
