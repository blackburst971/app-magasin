<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Computer extends Model
{
    protected $fillable = [
        'name', 'asset_tag', 'type', 'os', 'os_version',
        'serial_number', 'ip_address', 'processor', 'ram',
        'photo', 'notes',
    ];

    /* ── Relations ─────────────────────────────────────────────────────── */

    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function licenses(): BelongsToMany
    {
        return $this->belongsToMany(License::class, 'license_assignments')
            ->withPivot(['id', 'notes', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    /* ── Accesseurs ─────────────────────────────────────────────────────── */

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'desktop'    => 'PC de bureau',
            'laptop'     => 'PC portable',
            'imac'       => 'iMac',
            'macbook'    => 'MacBook',
            'mac_mini'   => 'Mac mini',
            'mac_studio' => 'Mac Studio',
            'server'     => 'Serveur',
            'tablet'     => 'Tablette',
            default      => 'Autre',
        };
    }

    public function getOsLabel(): string
    {
        return match($this->os) {
            'windows_10'     => 'Windows 10',
            'windows_11'     => 'Windows 11',
            'windows_server' => 'Windows Server',
            'macos'          => 'macOS',
            'linux'          => 'Linux',
            'chromeos'       => 'ChromeOS',
            default          => 'Autre',
        };
    }
}
