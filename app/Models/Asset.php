<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AssetStatus;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'asset_tag',
        'serial',
        'model',
        'manufacturer',
        'manufacturer_id',
        'location',
        'status',
        'is_reservable',
        'purchase_date',
        'purchase_cost',
        'notes',
        'public_description',
        'custom_fields',
        'image',
        'qr_code',
        'equipment_model_id',
        'supplier_id',
        'parent_asset_id',
        'checked_out_to_user_id',
        'expected_return_at',
    ];

    protected $casts = [
        'purchase_date'      => 'date',
        'purchase_cost'      => 'decimal:2',
        'is_reservable'      => 'boolean',
        'expected_return_at' => 'datetime',
        'custom_fields'      => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturerModel()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function equipmentModel()
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }

    public function checkedOutTo()
    {
        return $this->belongsTo(User::class, 'checked_out_to_user_id');
    }

    public function kits()
    {
        return $this->belongsToMany(Kit::class, 'kit_assets')->withTimestamps();
    }

    /** Équipement parent (si cet asset est un accessoire) */
    public function parentAsset()
    {
        return $this->belongsTo(Asset::class, 'parent_asset_id');
    }

    /** Accessoires rattachés à cet équipement */
    public function accessories()
    {
        return $this->hasMany(Asset::class, 'parent_asset_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    /** Fichiers publics (images + PDFs) attachés à cet équipement */
    public function files()
    {
        return $this->hasMany(AssetFile::class)->orderBy('created_at');
    }

    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'reservation_assets')
            ->withPivot('returned_at', 'checkout_at', 'good_condition', 'return_notes', 'returned_by_user_id', 'is_missing')
            ->withTimestamps();
    }

    public function movements()
    {
        return $this->hasMany(AssetMovement::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isAvailableForPeriod(\DateTime $start, \DateTime $end): bool
    {
        // Statut bloquant (hors service, perdu, etc.)
        $statuses = AssetStatus::allCached();
        $currentStatus = $statuses->firstWhere('key', $this->status);
        if ($currentStatus && $currentStatus->blocks_reservation) {
            return false;
        }

        // Conflit de dates avec une réservation approuvée ou active
        // (le fait qu'un équipement soit sorti mais hors de la période demandée ne bloque pas)
        $conflict = $this->reservations()
            ->whereIn('reservations.status', ['approved', 'active'])
            ->where(function ($q) use ($start, $end) {
                $q->where('reservations.start_date', '<', $end)
                  ->where('reservations.end_date', '>', $start);
            })
            ->exists();

        return !$conflict;
    }

    /**
     * Disponibilité détaillée pour une période donnée.
     *
     * 'available'   → aucun conflit
     * 'warning'     → alerte sans blocage (réservation en attente, ou équipement en retard de retour)
     * 'unavailable' → conflit dur : la réservation est refusée
     *
     * Règle métier : le fait qu'un équipement soit actuellement sorti ne bloque PAS
     * une réservation future si les dates ne se chevauchent pas. L'attribution physique
     * sera bloquée si l'équipement n'est pas retourné à temps.
     */
    public function getAvailabilityForPeriod(\DateTime $start, \DateTime $end): array
    {
        // 1. Statut bloquant (hors service, perdu, en maintenance…)
        $statuses = AssetStatus::allCached();
        $currentStatus = $statuses->firstWhere('key', $this->status);
        if ($currentStatus && $currentStatus->blocks_reservation) {
            return ['status' => 'unavailable', 'reason' => 'Équipement hors service (' . $currentStatus->label . ')'];
        }

        // 2. Conflit de dates dur : réservation approuvée ou active qui chevauche la période
        $hardConflict = $this->reservations()
            ->whereIn('reservations.status', ['approved', 'active'])
            ->where(function ($q) use ($start, $end) {
                $q->where('reservations.start_date', '<', $end)
                  ->where('reservations.end_date', '>', $start);
            })
            ->first();

        if ($hardConflict) {
            $reason = $hardConflict->status === 'active'
                ? 'En cours d\'utilisation sur cette période'
                : 'Déjà réservé et validé sur cette période';
            return ['status' => 'unavailable', 'reason' => $reason];
        }

        // 3. Équipement sorti et en retard de retour (date de fin dépassée, non rendu)
        //    → warning seulement, la réservation reste possible
        $overdueReturn = $this->reservations()
            ->where('reservations.status', 'active')
            ->wherePivotNotNull('checkout_at')
            ->wherePivotNull('returned_at')
            ->where('reservations.end_date', '<', now())
            ->exists();

        if ($overdueReturn) {
            return ['status' => 'warning', 'reason' => 'Cet équipement est en retard de retour — disponibilité non garantie'];
        }

        // 4. Conflit souple : réservation en attente de validation sur la période
        $softConflict = $this->reservations()
            ->where('reservations.status', 'pending')
            ->where(function ($q) use ($start, $end) {
                $q->where('reservations.start_date', '<', $end)
                  ->where('reservations.end_date', '>', $start);
            })
            ->exists();

        if ($softConflict) {
            return ['status' => 'warning', 'reason' => 'Demande en attente sur cette période (non encore validée)'];
        }

        return ['status' => 'available', 'reason' => null];
    }

    public function getStatusLabelAttribute(): string
    {
        $statuses = AssetStatus::allCached();
        $found = $statuses->firstWhere('key', $this->status);
        return $found ? $found->label : ($this->status ?? '');
    }

    public function getStatusColorAttribute(): string
    {
        $statuses = AssetStatus::allCached();
        $found = $statuses->firstWhere('key', $this->status);
        return $found ? $found->color : 'gray';
    }

    /** Scope : uniquement les assets réservables (pour les utilisateurs standard) */
    public function scopeReservable($query)
    {
        return $query->where('is_reservable', true);
    }
}
