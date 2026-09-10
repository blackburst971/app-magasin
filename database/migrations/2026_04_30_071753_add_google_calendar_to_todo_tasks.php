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
        Schema::table('todo_tasks', function (Blueprint $table) {
            $table->boolean('sync_to_calendar')->default(false)->after('position');
            $table->string('google_event_id')->nullable()->after('sync_to_calendar');
        });
    }

    public function down(): void
    {
        Schema::table('todo_tasks', function (Blueprint $table) {
            $table->dropColumn(['sync_to_calendar', 'google_event_id']);
        });
    }
};
