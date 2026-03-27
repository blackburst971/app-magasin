<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'email', 'phone', 'address', 'website', 'notes',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function equipmentModels(): HasMany
    {
        return $this->hasMany(EquipmentModel::class);
    }
}
