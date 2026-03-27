<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('supervisor_approved_at')->nullable()->after('supervisor_signature');
            $table->foreignId('supervisor_approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('supervisor_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['supervisor_approved_by']);
            $table->dropColumn(['supervisor_approved_at', 'supervisor_approved_by']);
        });
    }
};
