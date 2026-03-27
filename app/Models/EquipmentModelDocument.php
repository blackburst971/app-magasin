<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentModelDocument extends Model
{
    protected $fillable = [
        'equipment_model_id',
        'name',
        'original_name',
        'path',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // ─── Relations ───────────────────────────────────────────────────────
    public function equipmentModel(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class);
    }

    // ─── Accessors ───────────────────────────────────────────────────────
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return "{$bytes} o";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' Ko';
        return round($bytes / 1048576, 1) . ' Mo';
    }

    public function getUrlAttribute(): string
    {
        return \Illuminate\Support\Facades\Storage::url($this->path);
    }
}
