<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetComponent extends Model
{
    protected $fillable = [
        'asset_id',
        'name',
        'description',
        'quantity',
        'sort_order',
        'condition',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'sort_order' => 'integer',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
