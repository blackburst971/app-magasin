<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\AssetMovement;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@lycee.fr')->first();
        $user  = User::where('email', 'user@lycee.fr')->first();

        $categories = Category::all()->keyBy('name');

        // ── Équipements ───────────────────────────────────────────────
        $assets = [
            // Informatique
            ['name' => 'MacBook Pro 14"',        'asset_tag' => 'MAC-001', 'model' => 'MacBook Pro 14 M3',   'manufacturer' => 'Apple',   'category' => 'Informatique', 'location' => 'Salle 101', 'serial' => 'C02X4XXXXXX1', 'status' => 'available'],
            ['name' => 'MacBook Pro 14"',        'asset_tag' => 'MAC-002', 'model' => 'MacBook Pro 14 M3',   'manufacturer' => 'Apple',   'category' => 'Informatique', 'location' => 'Salle 101', 'serial' => 'C02X4XXXXXX2', 'status' => 'available'],
            ['name' => 'MacBook Air 13"',        'asset_tag' => 'MAC-003', 'model' => 'MacBook Air 13 M2',   'manufacturer' => 'Apple',   'category' => 'Informatique', 'location' => 'Salle 102', 'serial' => 'C02Y5XXXXXX1', 'status' => 'available'],
            ['name' => 'Dell Latitude 5540',     'asset_tag' => 'DEL-001', 'model' => 'Latitude 5540',       'manufacturer' => 'Dell',    'category' => 'Informatique', 'location' => 'Réserve',   'serial' => 'DLLT5540001',  'status' => 'available'],
            ['name' => 'Dell Latitude 5540',     'asset_tag' => 'DEL-002', 'model' => 'Latitude 5540',       'manufacturer' => 'Dell',    'category' => 'Informatique', 'location' => 'Réserve',   'serial' => 'DLLT5540002',  'status' => 'available'],
            ['name' => 'Raspberry Pi 5',         'asset_tag' => 'RPI-001', 'model' => 'Raspberry Pi 5 8GB',  'manufacturer' => 'RPi Ltd', 'category' => 'Informatique', 'location' => 'Labo',      'serial' => 'RPI5-0001',    'status' => 'available'],
            ['name' => 'Raspberry Pi 5',         'asset_tag' => 'RPI-002', 'model' => 'Raspberry Pi 5 8GB',  'manufacturer' => 'RPi Ltd', 'category' => 'Informatique', 'location' => 'Labo',      'serial' => 'RPI5-0002',    'status' => 'available'],
            ['name' => 'Raspberry Pi 5',         'asset_tag' => 'RPI-003', 'model' => 'Raspberry Pi 5 8GB',  'manufacturer' => 'RPi Ltd', 'category' => 'Informatique', 'location' => 'Labo',      'serial' => 'RPI5-0003',    'status' => 'available'],

            // Audiovisuel
            ['name' => 'Canon EOS R50',          'asset_tag' => 'CAM-001', 'model' => 'EOS R50',             'manufacturer' => 'Canon',   'category' => 'Audiovisuel',  'location' => 'Réserve AV', 'serial' => 'CANER50001', 'status' => 'available'],
            ['name' => 'Sony ZV-E10',            'asset_tag' => 'CAM-002', 'model' => 'ZV-E10',              'manufacturer' => 'Sony',    'category' => 'Audiovisuel',  'location' => 'Réserve AV', 'serial' => 'SONYZVE10001', 'status' => 'available'],
            ['name' => 'Trépied Manfrotto 190',  'asset_tag' => 'TRP-001', 'model' => '190XPRO',             'manufacturer' => 'Manfrotto','category' => 'Audiovisuel', 'location' => 'Réserve AV', 'serial' => null, 'status' => 'available'],
            ['name' => 'Trépied Manfrotto 190',  'asset_tag' => 'TRP-002', 'model' => '190XPRO',             'manufacturer' => 'Manfrotto','category' => 'Audiovisuel', 'location' => 'Réserve AV', 'serial' => null, 'status' => 'available'],
            ['name' => 'Micro Rode VideoMic',    'asset_tag' => 'MIC-001', 'model' => 'VideoMic Pro+',       'manufacturer' => 'Rode',    'category' => 'Audiovisuel',  'location' => 'Réserve AV', 'serial' => 'RODEVMP001', 'status' => 'available'],
            ['name' => 'Vidéoprojecteur Epson',  'asset_tag' => 'VPJ-001', 'model' => 'EB-X51',              'manufacturer' => 'Epson',   'category' => 'Audiovisuel',  'location' => 'Salle conf.', 'serial' => 'EPSEBX51001', 'status' => 'available'],

            // Réseau
            ['name' => 'Switch Cisco 24p',       'asset_tag' => 'SW-001',  'model' => 'Catalyst 2960',       'manufacturer' => 'Cisco',   'category' => 'Réseau',       'location' => 'Labo réseau', 'serial' => 'FTX1234A001', 'status' => 'available'],
            ['name' => 'Switch Cisco 24p',       'asset_tag' => 'SW-002',  'model' => 'Catalyst 2960',       'manufacturer' => 'Cisco',   'category' => 'Réseau',       'location' => 'Labo réseau', 'serial' => 'FTX1234A002', 'status' => 'available'],
            ['name' => 'Routeur pfSense',        'asset_tag' => 'RT-001',  'model' => 'SG-3100',             'manufacturer' => 'Netgate', 'category' => 'Réseau',       'location' => 'Labo réseau', 'serial' => 'NGSG3100001', 'status' => 'available'],
            ['name' => 'Kit câbles RJ45 (x10)',  'asset_tag' => 'CAB-001', 'model' => 'Cat.6 2m',            'manufacturer' => 'Generic', 'category' => 'Réseau',       'location' => 'Réserve',     'serial' => null, 'status' => 'available'],

            // Outillage
            ['name' => 'Multimètre Fluke 115',   'asset_tag' => 'OUT-001', 'model' => 'Fluke 115',           'manufacturer' => 'Fluke',   'category' => 'Outillage',    'location' => 'Atelier',     'serial' => 'FL115001', 'status' => 'available'],
            ['name' => 'Fer à souder Hakko',     'asset_tag' => 'OUT-002', 'model' => 'FX-888D',             'manufacturer' => 'Hakko',   'category' => 'Outillage',    'location' => 'Atelier',     'serial' => 'HAKFX888001', 'status' => 'available'],
            ['name' => 'Oscilloscope Rigol',     'asset_tag' => 'OUT-003', 'model' => 'DS1054Z',             'manufacturer' => 'Rigol',   'category' => 'Outillage',    'location' => 'Atelier',     'serial' => 'RIGDS1054001', 'status' => 'maintenance'],
        ];

        $createdAssets = [];
        foreach ($assets as $data) {
            $cat = $categories->get($data['category']);
            $asset = Asset::updateOrCreate(
                ['asset_tag' => $data['asset_tag']],
                [
                    'name'         => $data['name'],
                    'model'        => $data['model'],
                    'manufacturer' => $data['manufacturer'],
                    'serial'       => $data['serial'] ?? null,
                    'category_id'  => $cat?->id,
                    'location'     => $data['location'],
                    'status'       => $data['status'],
                    'purchase_date'=> Carbon::now()->subMonths(rand(3, 36))->toDateString(),
                ]
            );
            $createdAssets[$data['asset_tag']] = $asset;

            // Mouvement d'import
            AssetMovement::firstOrCreate(
                ['asset_id' => $asset->id, 'action' => 'import'],
                ['user_id' => $admin->id, 'notes' => 'Import initial (démo)']
            );
        }

        // ── Réservation en attente ─────────────────────────────────────
        $res1 = Reservation::create([
            'user_id'    => $user->id,
            'title'      => 'Projet photo BTS SIO',
            'start_date' => Carbon::now()->addDays(3)->toDateString(),
            'end_date'   => Carbon::now()->addDays(7)->toDateString(),
            'status'     => 'pending',
            'notes'      => 'Reportage sur les activités du lycée pour le journal interne.',
        ]);
        $res1->assets()->attach([
            $createdAssets['CAM-001']->id,
            $createdAssets['TRP-001']->id,
            $createdAssets['MIC-001']->id,
        ]);

        // ── Réservation validée ────────────────────────────────────────
        $res2 = Reservation::create([
            'user_id'     => $user->id,
            'title'       => 'TP Réseau avancé',
            'start_date'  => Carbon::now()->addDays(1)->toDateString(),
            'end_date'    => Carbon::now()->addDays(2)->toDateString(),
            'status'      => 'approved',
            'notes'       => 'Travaux pratiques sur la configuration VLAN.',
            'approved_by' => $admin->id,
            'approved_at' => Carbon::now()->subHours(2),
        ]);
        $res2->assets()->attach([
            $createdAssets['SW-001']->id,
            $createdAssets['SW-002']->id,
            $createdAssets['RT-001']->id,
        ]);

        // ── Réservation en cours (active) ──────────────────────────────
        $res3 = Reservation::create([
            'user_id'     => $user->id,
            'title'       => 'Stage développement web',
            'start_date'  => Carbon::now()->subDays(2)->toDateString(),
            'end_date'    => Carbon::now()->addDays(5)->toDateString(),
            'status'      => 'active',
            'approved_by' => $admin->id,
            'approved_at' => Carbon::now()->subDays(3),
            'checkout_at' => Carbon::now()->subDays(2),
        ]);
        $macBook = $createdAssets['MAC-001'];
        $res3->assets()->attach($macBook->id);
        $macBook->update(['status' => 'reserved']);
        AssetMovement::create([
            'asset_id'       => $macBook->id,
            'reservation_id' => $res3->id,
            'user_id'        => $admin->id,
            'action'         => 'checkout',
            'notes'          => 'Remis en mains propres',
        ]);

        // ── Réservation retournée (historique) ────────────────────────
        $res4 = Reservation::create([
            'user_id'     => $admin->id,
            'title'       => 'Présentation direction',
            'start_date'  => Carbon::now()->subDays(14)->toDateString(),
            'end_date'    => Carbon::now()->subDays(12)->toDateString(),
            'status'      => 'returned',
            'approved_by' => $admin->id,
            'approved_at' => Carbon::now()->subDays(15),
            'checkout_at' => Carbon::now()->subDays(14),
            'return_at'   => Carbon::now()->subDays(12),
        ]);
        $res4->assets()->attach($createdAssets['VPJ-001']->id, [
            'returned_at' => Carbon::now()->subDays(12),
        ]);

        $this->command->info('Données de démonstration créées : ' . count($assets) . ' équipements, 4 réservations');
    }
}
