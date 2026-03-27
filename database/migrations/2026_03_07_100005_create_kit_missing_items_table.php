<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_missing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            // L'utilisateur à qui on attribue la perte (peut être différent du réservant)
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            // Régularisation par l'admin
            $table->timestamp('regularized_at')->nullable();
            $table->text('regularization_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_missing_items');
    }
};
