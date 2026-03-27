<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Manufacturer;
use App\Models\EquipmentModel;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_models', function (Blueprint $table) {
            $table->foreignId('manufacturer_id')
                ->nullable()
                ->after('name')
                ->constrained('manufacturers')
                ->nullOnDelete();
        });

        // Migrate existing manufacturer strings → manufacturer_id
        EquipmentModel::whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->get()
            ->each(function ($model) {
                $mfr = Manufacturer::firstOrCreate(['name' => trim($model->manufacturer)]);
                $model->updateQuietly(['manufacturer_id' => $mfr->id]);
            });
    }

    public function down(): void
    {
        Schema::table('equipment_models', function (Blueprint $table) {
            $table->dropForeign(['manufacturer_id']);
            $table->dropColumn('manufacturer_id');
        });
    }
};
