<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementFormField extends Model
{
    protected $fillable = ['label', 'type', 'options', 'required', 'sort_order', 'active'];

    protected $casts = [
        'options'  => 'array',
        'required' => 'boolean',
        'active'   => 'boolean',
    ];
}
