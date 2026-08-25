<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProcurementFile extends Model
{
    protected $fillable = [
        'original_name',
        'description',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('procurement.files.download', $this->id);
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024)       return $bytes . ' o';
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' Ko';
        return round($bytes / 1048576, 1) . ' Mo';
    }

    public function getIconTypeAttribute(): string
    {
        return match (true) {
            str_contains($this->mime_type ?? '', 'pdf')    => 'pdf',
            str_contains($this->mime_type ?? '', 'excel')  => 'excel',
            str_contains($this->mime_type ?? '', 'sheet')  => 'excel',
            str_contains($this->mime_type ?? '', 'word')   => 'word',
            str_contains($this->mime_type ?? '', 'image')  => 'image',
            default                                         => 'file',
        };
    }
}
