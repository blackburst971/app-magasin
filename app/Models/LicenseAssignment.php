<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseAssignment extends Model
{
    protected $fillable = [
        'license_id', 'computer_id', 'assigned_by', 'assigned_at', 'notes',
    ];

    protected $casts = [
        'assigned_at' => 'date',
    ];

    /* ── Relations ─────────────────────────────────────────────────────── */

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
