<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('planned_absences', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->boolean('is_full_day')->default(true);
            $table->time('start_time')->nullable(); // null = full day
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('planned_absences'); }
};
