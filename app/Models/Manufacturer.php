<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Manufacturer extends Model
{
    protected $fillable = ['name', 'description', 'website', 'image'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        if (str_starts_with($this->image, 'http')) return $this->image;
        return Storage::url($this->image);
    }

    public function equipmentModels()
    {
        return $this->hasMany(EquipmentModel::class, 'manufacturer_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'manufacturer_id');
    }
}
