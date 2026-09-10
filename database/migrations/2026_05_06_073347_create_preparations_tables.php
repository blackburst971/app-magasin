<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepared_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->datetime('planned_start_at');
            $table->datetime('planned_end_at');
            $table->text('notes')->nullable();
            $table->string('status')->default('preparing'); // preparing | validated | cancelled
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->boolean('send_preparation_mail')->default(false);
            $table->boolean('send_checkout_mail')->default(false);
            $table->datetime('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('preparation_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained('preparations')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('previous_status')->default('available');
            $table->datetime('scanned_at');
            $table->timestamps();
            $table->unique(['preparation_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparation_assets');
        Schema::dropIfExists('preparations');
    }
};
