<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['kit_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_assets');
    }
};
