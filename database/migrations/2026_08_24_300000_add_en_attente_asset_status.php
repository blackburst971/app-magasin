<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('asset_statuses')->where('key', 'en_attente')->exists()) {
            return;
        }

        DB::table('asset_statuses')->insert([
            'key'                => 'en_attente',
            'label'              => 'En attente',
            'description'        => 'Équipement mis en attente par l\'administrateur. Non visible et non réservable par les utilisateurs.',
            'color'              => 'orange',
            'is_system'          => true,
            'blocks_reservation' => true,
            'is_active'          => true,
            'user_visible'       => false,
            'hide_from_users'    => true,
            'sort_order'         => 6,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('asset_statuses')->where('key', 'en_attente')->delete();
    }
};
