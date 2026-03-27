<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('equipment_model_id')->nullable()->after('model')
                  ->constrained('equipment_models')->nullOnDelete();
            $table->foreignId('checked_out_to_user_id')->nullable()->after('equipment_model_id')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('expected_return_at')->nullable()->after('checked_out_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeignIdFor('checked_out_to_user_id');
            $table->dropForeignIdFor('equipment_model_id');
            $table->dropColumn(['equipment_model_id', 'checked_out_to_user_id', 'expected_return_at']);
        });
    }
};
