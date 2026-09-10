<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset_statuses', function (Blueprint $table) {
            // Masque complètement les équipements portant ce statut côté utilisateurs
            // (non visibles dans la liste, non réservables)
            $table->boolean('hide_from_users')->default(false)->after('user_visible');
        });

        // Le statut "Retiré" masque les équipements aux utilisateurs par défaut
        DB::table('asset_statuses')->where('key', 'retired')->update(['hide_from_users' => true]);
    }

    public function down(): void
    {
        Schema::table('asset_statuses', function (Blueprint $table) {
            $table->dropColumn('hide_from_users');
        });
    }
};
