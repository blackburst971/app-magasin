<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('asset_tag')->nullable()->unique();
            $table->enum('type', [
                'desktop', 'laptop', 'imac', 'macbook',
                'mac_mini', 'mac_studio', 'server', 'tablet', 'other',
            ])->default('desktop');
            $table->enum('os', [
                'windows_10', 'windows_11', 'windows_server',
                'macos', 'linux', 'chromeos', 'other',
            ])->default('windows_11');
            $table->string('os_version')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('processor')->nullable();
            $table->string('ram')->nullable();        // ex: "16 Go"
            $table->string('photo')->nullable();      // path dans storage
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computers');
    }
};
