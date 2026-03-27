<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EquipmentModel extends Model
{
    protected $fillable = ['name', 'manufacturer', 'manufacturer_id', 'category_id', 'supplier_id', 'description', 'image'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturerModel()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'equipment_model_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function documents()
    {
        return $this->hasMany(EquipmentModelDocument::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        if (str_starts_with($this->image, 'http')) return $this->image;
        return Storage::url($this->image);
    }

    // Propagate image to all linked assets
    public function propagateImageToAssets(): void
    {
        $this->assets()->update(['image' => $this->image]);
    }
}
