<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitCategory extends Model
{
    protected $fillable = ['name', 'color', 'description', 'position'];

    public function kits()
    {
        return $this->hasMany(Kit::class, 'kit_category_id');
    }
}
