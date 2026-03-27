<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('parent_asset_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('assets')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Asset::class, 'parent_asset_id');
        });
    }
};
