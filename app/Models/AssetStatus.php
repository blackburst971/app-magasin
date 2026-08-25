<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AssetStatus extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'color',
        'is_system',
        'blocks_reservation',
        'is_active',
        'user_visible',
        'hide_from_users',
        'sort_order',
    ];

    protected $casts = [
        'is_system'          => 'boolean',
        'blocks_reservation' => 'boolean',
        'is_active'          => 'boolean',
        'user_visible'       => 'boolean',
        'hide_from_users'    => 'boolean',
        'sort_order'         => 'integer',
    ];

    /**
     * Retourne tous les statuts actifs, mis en cache 5 minutes.
     */
    public static function allCached(): \Illuminate\Support\Collection
    {
        return Cache::remember('asset_statuses', 300, function () {
            return static::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();
        });
    }

    /**
     * Invalide le cache des statuts.
     */
    public static function clearCache(): void
    {
        Cache::forget('asset_statuses');
    }

    /**
     * Palette de couleurs disponibles pour les badges.
     */
    public static function colorPalette(): array
    {
        return [
            'green'  => ['label' => 'Vert',   'bg' => 'bg-green-100',  'text' => 'text-green-800',  'dot' => 'bg-green-500'],
            'blue'   => ['label' => 'Bleu',   'bg' => 'bg-blue-100',   'text' => 'text-blue-800',   'dot' => 'bg-blue-500'],
            'purple' => ['label' => 'Violet', 'bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'dot' => 'bg-purple-500'],
            'yellow' => ['label' => 'Jaune',  'bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'dot' => 'bg-yellow-500'],
            'orange' => ['label' => 'Orange', 'bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'dot' => 'bg-orange-500'],
            'red'    => ['label' => 'Rouge',  'bg' => 'bg-red-100',    'text' => 'text-red-800',    'dot' => 'bg-red-500'],
            'gray'   => ['label' => 'Gris',   'bg' => 'bg-gray-100',   'text' => 'text-gray-800',   'dot' => 'bg-gray-500'],
            'indigo' => ['label' => 'Indigo', 'bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'dot' => 'bg-indigo-500'],
            'pink'   => ['label' => 'Rose',   'bg' => 'bg-pink-100',   'text' => 'text-pink-800',   'dot' => 'bg-pink-500'],
            'teal'   => ['label' => 'Cyan',   'bg' => 'bg-teal-100',   'text' => 'text-teal-800',   'dot' => 'bg-teal-500'],
        ];
    }
}
