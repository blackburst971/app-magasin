<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IpDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'name',
        'device_type',
        'mac_address',
        'ip_address',
        'subnet_mask',
        'gateway',
        'dns_primary',
        'dns_secondary',
        'place_id',
        'location',
        'brand',
        'model',
        'notes',
        'status',
        'photo',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function ipChangeRequests()
    {
        return $this->hasMany(IpDeviceIpChangeRequest::class)->latest();
    }

    public function pendingIpChangeRequest()
    {
        return $this->hasOne(IpDeviceIpChangeRequest::class)->where('status', 'pending')->latest();
    }
}
