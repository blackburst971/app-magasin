<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementHistory extends Model
{
    protected $fillable = ['procurement_item_id', 'user_id', 'action', 'snapshot', 'changes'];

    protected $casts = [
        'snapshot' => 'array',
        'changes'  => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
