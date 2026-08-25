<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementItem extends Model
{
    protected $fillable = [
        'designation',
        'type',
        'quantity',
        'unit',
        'reference',
        'manufacturer',
        'supplier',
        'unit_price',
        'product_url',
        'notes',
        'status',
        'priority',
        'extra_fields',
        'created_by_user_id',
        'updated_by_user_id',
        'archived_at',
        'archived_by_user_id',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'unit_price'  => 'float',
        'extra_fields'=> 'array',
        'archived_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    public function getTotalPriceAttribute(): ?float
    {
        if ($this->unit_price === null) return null;
        return round($this->unit_price * $this->quantity, 2);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'materiel'    => 'Matériel',
            'consommable' => 'Consommable',
            'logiciel'    => 'Logiciel',
            default       => 'Autre',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'besoin'      => 'Besoin identifié',
            'en_commande' => 'En cours de commande',
            'commande'    => 'Commandé',
            'livre'       => 'Livré',
            'annule'      => 'Annulé',
            default       => $status,
        };
    }

    public static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'haute' => 'Haute',
            'basse' => 'Basse',
            default => 'Normale',
        };
    }
}
