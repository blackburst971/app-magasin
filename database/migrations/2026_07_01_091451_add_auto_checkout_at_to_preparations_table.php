<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('preparations', function (Blueprint $table) {
            $table->timestamp('auto_checkout_at')->nullable()->after('planned_end_at');
        });
    }

    public function down(): void
    {
        Schema::table('preparations', function (Blueprint $table) {
            $table->dropColumn('auto_checkout_at');
        });
    }
};
