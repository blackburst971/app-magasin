<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->json('extra_fields')->nullable()->after('notes');
            $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('updated_by_user_id');
            $table->foreignId('archived_by_user_id')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->dropForeign(['updated_by_user_id']);
            $table->dropForeign(['archived_by_user_id']);
            $table->dropColumn(['extra_fields', 'updated_by_user_id', 'archived_at', 'archived_by_user_id']);
        });
    }
};
