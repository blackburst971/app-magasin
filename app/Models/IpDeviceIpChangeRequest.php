<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IpDeviceIpChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_device_id',
        'requested_by',
        'old_ip_address',
        'old_subnet_mask',
        'old_gateway',
        'old_dns_primary',
        'old_dns_secondary',
        'new_ip_address',
        'new_subnet_mask',
        'new_gateway',
        'new_dns_primary',
        'new_dns_secondary',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function ipDevice()
    {
        return $this->belongsTo(IpDevice::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
