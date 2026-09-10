<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('label_to_create')->default(false)->after('return_check_note');
            $table->boolean('label_to_apply')->default(false)->after('label_to_create');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['label_to_create', 'label_to_apply']);
        });
    }
};
