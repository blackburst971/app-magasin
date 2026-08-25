<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_form_id',
        'type',
        'label',
        'placeholder',
        'options',
        'required',
        'order',
        'col_span',
    ];

    protected $casts = [
        'options'  => 'array',
        'required' => 'boolean',
        'col_span' => 'integer',
    ];

    public function form()
    {
        return $this->belongsTo(CheckoutForm::class, 'checkout_form_id');
    }
}
