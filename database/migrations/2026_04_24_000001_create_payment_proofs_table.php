<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique(); // Token único para el link público
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->comment('Agente que envió el link')->constrained()->onDelete('cascade');
            $table->foreignId('downloaded_by')->nullable()->comment('Agente que descargó el soporte')->constrained('users')->nullOnDelete();
            $table->string('phone_number', 20);
            $table->string('client_name')->nullable();
            $table->string('file_path')->nullable(); // Ruta del archivo subido
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->enum('status', ['pending', 'uploaded', 'downloaded', 'expired'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
