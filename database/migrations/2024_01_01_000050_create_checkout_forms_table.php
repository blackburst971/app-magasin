<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('checkout_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_form_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['text', 'textarea', 'select', 'checkbox', 'date', 'number', 'email', 'phone'])->default('text');
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('checkout_form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('responses');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_form_responses');
        Schema::dropIfExists('checkout_form_fields');
        Schema::dropIfExists('checkout_forms');
    }
};
