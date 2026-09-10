<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lie le champ "emplacement" des équipements aux lieux formels (places).
     * Migre automatiquement les données existantes :
     *   1. Si l'asset a une affectation de lieu active  → place_id = celle-ci
     *   2. Si assets.location correspond au nom d'un lieu → place_id = ce lieu
     * Dans les deux cas, location (texte) est synchronisé au nom du lieu.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('place_id')->nullable()->after('location');
            $table->foreign('place_id')->references('id')->on('places')->nullOnDelete();
        });

        // 1. Assets avec une affectation de lieu active (la plus récente gagne)
        $assignments = DB::table('asset_place_assignments as apa')
            ->join('places as p', 'apa.place_id', '=', 'p.id')
            ->whereNull('apa.returned_at')
            ->select('apa.asset_id', 'apa.place_id', 'p.name as place_name')
            ->orderBy('apa.assigned_at', 'desc')
            ->get()
            ->unique('asset_id');

        foreach ($assignments as $row) {
            DB::table('assets')
                ->where('id', $row->asset_id)
                ->whereNull('place_id')
                ->update([
                    'place_id' => $row->place_id,
                    'location' => $row->place_name,
                ]);
        }

        // 2. Assets dont location (texte) correspond exactement au nom d'un lieu
        $places = DB::table('places')->get(['id', 'name']);
        foreach ($places as $place) {
            DB::table('assets')
                ->whereNull('place_id')
                ->where('location', $place->name)
                ->update(['place_id' => $place->id]);
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
            $table->dropColumn('place_id');
        });
    }
};
