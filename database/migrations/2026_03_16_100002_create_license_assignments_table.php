<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->foreignId('computer_id')->nullable()->constrained('computers')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users');
            $table->date('assigned_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Une seule attribution par couple licence + poste
            $table->unique(['license_id', 'computer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_assignments');
    }
};
