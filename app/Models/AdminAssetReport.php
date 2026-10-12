<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminAssetReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'reservation_id',
        'created_by',
        'type',
        'title',
        'content',
        'return_condition',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
