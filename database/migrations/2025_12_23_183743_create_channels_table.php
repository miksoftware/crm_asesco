<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('instance_name')->unique();
            $table->string('phone_number')->nullable();
            $table->string('token')->nullable();
            $table->enum('status', ['disconnected', 'connecting', 'connected', 'qr_code'])->default('disconnected');
            $table->enum('integration', ['WHATSAPP-BAILEYS', 'WHATSAPP-BUSINESS'])->default('WHATSAPP-BAILEYS');
            $table->text('qr_code')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pivot table for channel users
        Schema::create('channel_user', function (Blueprint $table) {
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['channel_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_user');
        Schema::dropIfExists('channels');
    }
};
