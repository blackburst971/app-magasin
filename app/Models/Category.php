<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'image', 'color'];

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function getAssetsCountAttribute(): int
    {
        return $this->assets()->count();
    }

    public function getAvailableAssetsCountAttribute(): int
    {
        return $this->assets()->where('status', 'available')->count();
    }
}
