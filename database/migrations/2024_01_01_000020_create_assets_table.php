<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('asset_tag')->unique();
            $table->string('serial')->nullable();
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['available', 'reserved', 'checked_out', 'maintenance', 'retired'])->default('available');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('image')->nullable();
            $table->string('qr_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
