<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'name',
        'location',
        'capacity',
        'description',
        'is_reservable',
        'color',
    ];

    protected $casts = [
        'is_reservable' => 'boolean',
        'capacity'      => 'integer',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }

    /**
     * Verifie si la salle est disponible pour une periode donnee.
     */
    public function isAvailableForPeriod(\DateTime $start, \DateTime $end, ?int $excludeId = null): bool
    {
        $query = $this->reservations()
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->doesntExist();
    }
}
