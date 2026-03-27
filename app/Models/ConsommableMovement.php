<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsommableMovement extends Model
{
    protected $table = 'consommable_movements';

    protected $fillable = [
        'consommable_id',
        'old_quantity',
        'new_quantity',
        'quantity_change',
        'user_id',
        'action',
        'consommable_request_id',
        'notes',
    ];

    protected $casts = [
        'old_quantity'    => 'integer',
        'new_quantity'    => 'integer',
        'quantity_change' => 'integer',
    ];

    // ─── Relations ───────────────────────────────────────────────────────
    public function consommable(): BelongsTo
    {
        return $this->belongsTo(Consommable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ConsommableRequest::class, 'consommable_request_id');
    }

    // ─── Accessors ───────────────────────────────────────────────────────
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'import'           => 'Import CSV',
            'add'              => 'Ajout stock',
            'remove'           => 'Retrait stock',
            'request_approved' => 'Demande approuvée',
            'edit'             => 'Modification',
            default            => $this->action,
        };
    }
}
