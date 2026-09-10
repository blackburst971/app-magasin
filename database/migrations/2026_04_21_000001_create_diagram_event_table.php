<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('diagram_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained('diagrams')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['diagram_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_event');
    }
};
