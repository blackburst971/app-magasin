<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            // L'équipement qui est affecté (celui qui "part")
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            // L'équipement destinataire (celui qui "reçoit")
            $table->foreignId('assigned_to_asset_id')->constrained('assets')->cascadeOnDelete();
            // Qui a effectué l'attribution
            $table->foreignId('assigned_by_user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
