<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('supervisor_id')->nullable(); // qui doit signer
            $table->string('purpose', 20)->default('supervisor'); // supervisor | admin_proxy
            $table->text('signature')->nullable();   // data URL reçue du mobile
            $table->json('context')->nullable();     // infos affichées sur la page mobile
            $table->string('status', 16)->default('active'); // active | completed | expired
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_sessions');
    }
};
