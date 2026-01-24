<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('phone_number', 20);
            $table->string('name')->nullable();
            $table->string('val1')->nullable(); // Variable personalizada 1
            $table->string('val2')->nullable(); // Variable personalizada 2
            $table->enum('status', ['pending', 'sent', 'failed', 'invalid'])->default('pending');
            $table->text('error_message')->nullable();
            $table->string('message_id')->nullable(); // ID del mensaje en Evolution API
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
