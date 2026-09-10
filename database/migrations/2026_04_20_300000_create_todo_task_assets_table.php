<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_task_assets', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('todo_tasks')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->primary(['task_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_task_assets');
    }
};
