<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Projets ──────────────────────────────────────────────────────────
        Schema::create('todo_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#6366f1'); // hex couleur
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_shared')->default(false); // visible par tous
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // ── Étiquettes (labels) ───────────────────────────────────────────────
        Schema::create('todo_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#64748b');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // ── Tâches ────────────────────────────────────────────────────────────
        Schema::create('todo_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('todo_projects')->cascadeOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('todo_tasks')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['none', 'low', 'medium', 'high', 'urgent'])->default('none');
            $table->enum('status', ['todo', 'in_progress', 'done'])->default('todo');
            $table->integer('position')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // ── Pivot tâches ↔ étiquettes ─────────────────────────────────────────
        Schema::create('todo_task_labels', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('todo_tasks')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('todo_labels')->cascadeOnDelete();
            $table->primary(['task_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_task_labels');
        Schema::dropIfExists('todo_tasks');
        Schema::dropIfExists('todo_labels');
        Schema::dropIfExists('todo_projects');
    }
};
