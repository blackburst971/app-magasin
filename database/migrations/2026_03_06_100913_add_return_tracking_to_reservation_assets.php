<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            $table->timestamp('checkout_at')->nullable()->after('returned_at');
            $table->boolean('good_condition')->nullable()->after('checkout_at');
            $table->text('return_notes')->nullable()->after('good_condition');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            $table->dropColumn(['checkout_at', 'good_condition', 'return_notes']);
        });
    }
};
