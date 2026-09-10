<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recycling_items', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('extra_fields');
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('recycling_items', function (Blueprint $table) {
            $table->dropForeign(['archived_by_user_id']);
            $table->dropColumn(['archived_at', 'archived_by_user_id']);
        });
    }
};
