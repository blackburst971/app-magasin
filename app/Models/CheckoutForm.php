<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutForm extends Model
{
    use HasFactory;

    // status: 'inactive' | 'optional' | 'required_before' | 'required_after'
    protected $fillable = ['name', 'description', 'status', 'created_by'];

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

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'inactive');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'inactive'        => 'Inactif',
            'optional'        => 'Optionnel',
            'required_before' => 'Obligatoire avant réservation',
            'required_after'  => 'Obligatoire après réservation',
            default           => 'Inconnu',
        };
    }
}
