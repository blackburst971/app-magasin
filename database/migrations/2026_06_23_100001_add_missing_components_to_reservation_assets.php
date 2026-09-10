<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            // IDs des composants manquants au moment du retour (JSON array)
            $table->json('missing_component_ids')->nullable()->after('verification_note');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            $table->dropColumn('missing_component_ids');
        });
    }
};
