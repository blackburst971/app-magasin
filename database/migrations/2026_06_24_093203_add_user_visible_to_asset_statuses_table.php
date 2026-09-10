<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_statuses', function (Blueprint $table) {
            $table->boolean('user_visible')->default(true)->after('is_active');
        });

        // Le statut "Retiré" ne doit pas être visible par les utilisateurs
        DB::table('asset_statuses')
            ->where('key', 'retired')
            ->update(['user_visible' => false]);
    }

    public function down(): void
    {
        Schema::table('asset_statuses', function (Blueprint $table) {
            $table->dropColumn('user_visible');
        });
    }
};
