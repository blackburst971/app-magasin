<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter la nouvelle colonne JSON si elle n'existe pas encore
        if (!Schema::hasColumn('computers', 'ip_addresses')) {
            Schema::table('computers', function (Blueprint $table) {
                $table->json('ip_addresses')->nullable()->after('ip_address');
            });
        }

        // 2. Migrer les données existantes (uniquement si l'ancienne colonne existe encore)
        if (Schema::hasColumn('computers', 'ip_address')) {
            DB::table('computers')
                ->whereNotNull('ip_address')
                ->where('ip_address', '!=', '')
                ->orderBy('id')
                ->each(function ($computer) {
                    // Ne migrer que si pas déjà fait
                    if (empty($computer->ip_addresses)) {
                        DB::table('computers')
                            ->where('id', $computer->id)
                            ->update(['ip_addresses' => json_encode([
                                ['ip' => $computer->ip_address, 'description' => ''],
                            ])]);
                    }
                });

            // 3. Supprimer l'ancienne colonne
            Schema::table('computers', function (Blueprint $table) {
                $table->dropColumn('ip_address');
            });
        }
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('ip_addresses');
        });

        // Restaurer la première IP de chaque poste
        DB::table('computers')
            ->whereNotNull('ip_addresses')
            ->orderBy('id')
            ->each(function ($computer) {
                $ips = json_decode($computer->ip_addresses, true);
                if (!empty($ips[0]['ip'])) {
                    DB::table('computers')
                        ->where('id', $computer->id)
                        ->update(['ip_address' => $ips[0]['ip']]);
                }
            });

        Schema::table('computers', function (Blueprint $table) {
            $table->dropColumn('ip_addresses');
        });
    }
};
