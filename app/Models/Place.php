<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $fillable = ['name', 'description', 'color', 'is_storage'];

    protected $casts = [
        'is_storage' => 'boolean',
    ];

    public function activeAssignments()
    {
        return $this->hasMany(AssetPlaceAssignment::class)->whereNull('returned_at');
    }

    public function assignments()
    {
        return $this->hasMany(AssetPlaceAssignment::class);
    }
}
