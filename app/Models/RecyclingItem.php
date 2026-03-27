<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RecyclingItem extends Model
{
    protected $fillable = [
        'asset_id', 'designation', 'serial_number', 'reference',
        'brand', 'model_name', 'quantity', 'notes', 'photo_path',
        'extra_fields', 'created_by_user_id',
    ];

    protected $casts = ['extra_fields' => 'array'];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) return null;
        if (str_starts_with($this->photo_path, 'http')) return $this->photo_path;
        return Storage::url($this->photo_path);
    }
}
