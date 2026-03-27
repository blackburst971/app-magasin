<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutFormResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_form_id',
        'reservation_id',
        'user_id',
        'responses',
        'submitted_at',
    ];

    protected $casts = [
        'responses' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(CheckoutForm::class, 'checkout_form_id');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
