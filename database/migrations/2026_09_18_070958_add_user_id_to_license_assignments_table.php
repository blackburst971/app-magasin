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
        Schema::table('license_assignments', function (Blueprint $table) {
            // Ajouter user_id pour permettre l'attribution de licences aux utilisateurs
            // Une licence peut être attribuée soit à un computer_id, soit à un user_id (mais pas les deux)
            $table->unsignedBigInteger('user_id')->nullable()->after('computer_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_assignments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
