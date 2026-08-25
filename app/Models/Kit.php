<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kit extends Model
{
    protected $fillable = ['name', 'description', 'notes', 'condition_note', 'image', 'status', 'is_reservable', 'kit_category_id'];

    protected $casts = ['is_reservable' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(KitCategory::class, 'kit_category_id');
    }

    /** Équipements composant ce kit */
    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'kit_assets')->withTimestamps();
    }

    /** Réservations liées à ce kit */
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'kit_id');
    }

    /** Tous les éléments manquants (régularisés et non régularisés) */
    public function missingItems()
    {
        return $this->hasMany(KitMissingItem::class, 'kit_id');
    }

    /** Éléments manquants en attente de régularisation */
    public function pendingMissingItems()
    {
        return $this->missingItems()->whereNull('regularized_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available'   => 'Disponible',
            'partial'     => 'Incomplet',
            'unavailable' => 'Indisponible',
            default       => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'available'   => 'green',
            'partial'     => 'yellow',
            'unavailable' => 'red',
            default       => 'gray',
        };
    }
}
