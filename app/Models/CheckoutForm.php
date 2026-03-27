<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutForm extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active', 'is_required_for_reservation', 'created_by'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_required_for_reservation' => 'boolean',
    ];

    public function fields()
    {
        return $this->hasMany(CheckoutFormField::class)->orderBy('order');
    }

    public function responses()
    {
        return $this->hasMany(CheckoutFormResponse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
