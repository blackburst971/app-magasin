<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('provider')->nullable();    // prestataire / technicien
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('started_at');
            $table->date('expected_return_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'resolved'])->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
