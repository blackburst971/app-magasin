<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('asset_statuses')->where('key', 'en_preparation')->exists()) {
            DB::table('asset_statuses')->insert([
                'key'               => 'en_preparation',
                'label'             => 'En préparation',
                'color'             => 'blue',
                'blocks_reservation'=> true,
                'is_system'         => true,
                'sort_order'        => 25,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('asset_statuses')->where('key', 'en_preparation')->delete();
    }
};
