<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputerIpChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'computer_id',
        'requested_by',
        'old_ip_addresses',
        'new_ip_addresses',
        'old_subnet_mask',
        'new_subnet_mask',
        'old_gateway',
        'new_gateway',
        'old_dns_primary',
        'new_dns_primary',
        'old_dns_secondary',
        'new_dns_secondary',
        'status',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'archived',
        'archived_at',
        'archived_by',
    ];

    protected $casts = [
        'old_ip_addresses' => 'array',
        'new_ip_addresses' => 'array',
        'reviewed_at' => 'datetime',
        'archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
