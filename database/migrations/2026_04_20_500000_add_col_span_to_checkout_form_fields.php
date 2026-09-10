<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_form_fields', function (Blueprint $table) {
            $table->unsignedTinyInteger('col_span')->default(1)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_form_fields', function (Blueprint $table) {
            $table->dropColumn('col_span');
        });
    }
};
