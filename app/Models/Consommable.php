<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consommable extends Model
{
    protected $fillable = [
        'name',
        'reference',
        'unit',
        'quantity',
        'min_stock',
        'category_id',
        'is_requestable',
        'unit_cost',
        'description',
        'image',
        'low_stock_alerted_at',
    ];

    protected $casts = [
        'quantity'             => 'integer',
        'min_stock'            => 'integer',
        'is_requestable'       => 'boolean',
        'unit_cost'            => 'decimal:2',
        'low_stock_alerted_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ConsommableRequest::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ConsommableMovement::class);
    }

    // ─── Accessors ───────────────────────────────────────────────────────
    public function getIsLowStockAttribute(): bool
    {
        return $this->min_stock > 0 && $this->quantity <= $this->min_stock;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity <= 0;
    }

    public function getStockValueAttribute(): float
    {
        return round($this->quantity * ($this->unit_cost ?? 0), 2);
    }
}
