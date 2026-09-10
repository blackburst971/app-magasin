<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            $table->boolean('pending_verification')->default(false)->after('is_missing');
            $table->text('verification_note')->nullable()->after('pending_verification');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            $table->dropColumn(['pending_verification', 'verification_note']);
        });
    }
};
