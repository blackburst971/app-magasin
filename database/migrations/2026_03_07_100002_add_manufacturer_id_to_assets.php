<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Manufacturer;
use App\Models\Asset;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('manufacturer_id')
                ->nullable()
                ->after('model')
                ->constrained('manufacturers')
                ->nullOnDelete();
        });

        // Migrate existing manufacturer strings → manufacturer_id
        Asset::whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->get()
            ->each(function ($asset) {
                $mfr = Manufacturer::firstOrCreate(['name' => trim($asset->manufacturer)]);
                $asset->updateQuietly(['manufacturer_id' => $mfr->id]);
            });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['manufacturer_id']);
            $table->dropColumn('manufacturer_id');
        });
    }
};
