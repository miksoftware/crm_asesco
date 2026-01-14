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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number', 20);
            $table->string('name')->nullable();
            $table->string('push_name')->nullable();
            $table->string('profile_picture', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('labels')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Índices
            $table->unique(['channel_id', 'phone_number'], 'unique_channel_phone');
            $table->index('phone_number', 'idx_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
