<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparation_assets', function (Blueprint $table) {
            $table->foreignId('kit_id')->nullable()->after('asset_id')->constrained('kits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preparation_assets', function (Blueprint $table) {
            $table->dropForeign(['kit_id']);
            $table->dropColumn('kit_id');
        });
    }
};
