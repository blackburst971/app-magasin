<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AssetFile extends Model
{
    protected $fillable = [
        'asset_id',
        'name',
        'path',
        'mime_type',
        'size',
        'type',
        'uploaded_by',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function getFormattedSizeAttribute(): string
    {
        if (!$this->size) return '';
        if ($this->size < 1024)        return $this->size . ' o';
        if ($this->size < 1_048_576)   return round($this->size / 1024, 1) . ' Ko';
        return round($this->size / 1_048_576, 1) . ' Mo';
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function getIsPdfAttribute(): bool
    {
        return str_contains($this->mime_type ?? '', 'pdf');
    }
}
