<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procurement_item_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->json('snapshot');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->foreign('procurement_item_id')->references('id')->on('procurement_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_history');
    }
};
