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
        Schema::table('preparations', function (Blueprint $table) {
            // 'free'        = préparation classique (libre ou liée à une résa avec ajout auto)
            // 'reservation' = préparation depuis une résa, scan obligatoire équipement par équipement
            $table->string('mode')->default('free')->after('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::table('preparations', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
