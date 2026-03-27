<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recycling_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('designation');
            $table->string('serial_number')->nullable();
            $table->string('reference')->nullable();
            $table->string('brand')->nullable();
            $table->string('model_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('extra_fields')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycling_items');
    }
};
