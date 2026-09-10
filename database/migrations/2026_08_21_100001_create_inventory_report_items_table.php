<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('asset_tag');
            $table->string('asset_name');
            $table->string('model_name')->nullable();
            $table->string('location')->nullable();
            $table->string('serial')->nullable();
            $table->enum('scan_status', ['present', 'missing', 'pending'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_report_items');
    }
};
