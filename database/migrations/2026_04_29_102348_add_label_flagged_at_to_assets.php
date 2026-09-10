<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->datetime('label_flagged_at')->nullable()->after('label_to_apply');
        });

        // Initialiser à updated_at pour les assets déjà flaggés (meilleure approximation disponible)
        DB::table('assets')
            ->where(fn($q) => $q->where('label_to_create', true)->orWhere('label_to_apply', true))
            ->update(['label_flagged_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('label_flagged_at');
        });
    }
};
