<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ajouter le statut "Présent dans un kit"
        DB::table('asset_statuses')->insert([
            'key'                => 'in_kit',
            'label'              => 'Présent dans un kit',
            'color'              => 'blue',
            'is_system'          => true,
            'blocks_reservation' => true,  // Un équipement dans un kit ne peut pas être réservé seul
            'is_active'          => true,
            'user_visible'       => true,
            'hide_from_users'    => false,
            'sort_order'         => 6,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('asset_statuses')->where('key', 'in_kit')->delete();
    }
};
