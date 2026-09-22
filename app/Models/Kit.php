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

    /**
     * Retourne le statut dynamique du kit basé sur les réservations actives.
     * Similaire au système des Assets.
     */
    public function getCurrentStatusAttribute(): string
    {
        // Charger les équipements du kit
        $kitAssetIds = $this->assets->pluck('id');

        if ($kitAssetIds->isEmpty()) {
            return $this->status ?? 'available';
        }

        // 1. Vérifier s'il y a une réservation active via kit_id OU via les équipements du kit
        $activeReservation = $this->reservations()
            ->where('status', 'active')
            ->whereNotNull('checkout_at')
            ->whereNull('return_at')
            ->first();

        // Si pas de réservation directe par kit_id, chercher via les équipements
        if (!$activeReservation) {
            // Chercher une réservation active qui contient TOUS les équipements du kit scannés
            $activeReservation = \App\Models\Reservation::where('status', 'active')
                ->whereNull('return_at')
                ->whereHas('assets', function($q) use ($kitAssetIds) {
                    // Au moins un équipement du kit scanné
                    $q->whereIn('assets.id', $kitAssetIds)
                      ->wherePivotNotNull('checkout_at');
                })
                ->withCount(['assets' => function($q) use ($kitAssetIds) {
                    // Compter combien d'équipements du kit sont scannés
                    $q->whereIn('assets.id', $kitAssetIds)
                      ->wherePivotNotNull('checkout_at');
                }])
                ->get()
                ->first(function($reservation) use ($kitAssetIds) {
                    // Vérifier si TOUS les équipements du kit sont scannés
                    return $reservation->assets_count >= $kitAssetIds->count();
                });
        }

        if ($activeReservation) {
            // Vérifier si c'est en retard
            if ($activeReservation->end_date->isPast()) {
                return 'overdue'; // En retard
            }
            return 'checked_out'; // Sorti
        }

        // 2. Vérifier s'il y a une réservation approuvée (prête à être retirée)
        $approvedReservation = $this->reservations()
            ->where('status', 'approved')
            ->where('start_date', '<=', now()->addDays(7))
            ->first();

        // Si pas de réservation directe par kit_id, chercher via les équipements
        if (!$approvedReservation) {
            $approvedReservation = \App\Models\Reservation::where('status', 'approved')
                ->where('start_date', '<=', now()->addDays(7))
                ->whereHas('assets', function($q) use ($kitAssetIds) {
                    $q->whereIn('assets.id', $kitAssetIds);
                })
                ->withCount(['assets' => function($q) use ($kitAssetIds) {
                    $q->whereIn('assets.id', $kitAssetIds);
                }])
                ->get()
                ->first(function($reservation) use ($kitAssetIds) {
                    return $reservation->assets_count >= $kitAssetIds->count();
                });
        }

        if ($approvedReservation) {
            return 'reserved'; // Réservé
        }

        // 3. Vérifier s'il y a des éléments manquants
        if ($this->pendingMissingItems()->exists()) {
            return 'partial'; // Incomplet
        }

        // 4. Vérifier le statut statique (disponible, indisponible)
        if ($this->status === 'unavailable') {
            return 'unavailable'; // Indisponible (défini manuellement)
        }

        // 5. Disponible par défaut
        return 'available';
    }

    public function getStatusLabelAttribute(): string
    {
        $currentStatus = $this->current_status;

        return match ($currentStatus) {
            'available'   => 'Disponible',
            'reserved'    => 'Réservé',
            'checked_out' => 'Sorti',
            'overdue'     => 'En retard',
            'partial'     => 'Incomplet',
            'unavailable' => 'Indisponible',
            default       => $this->status ?? 'Disponible',
        };
    }

    public function getStatusColorAttribute(): string
    {
        $currentStatus = $this->current_status;

        return match ($currentStatus) {
            'available'   => 'green',
            'reserved'    => 'blue',
            'checked_out' => 'purple',
            'overdue'     => 'red',
            'partial'     => 'yellow',
            'unavailable' => 'gray',
            default       => 'gray',
        };
    }
}
