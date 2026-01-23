<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores mappings between WhatsApp LIDs (Link IDs) and real phone numbers.
     * WhatsApp uses LIDs as internal privacy identifiers that cannot be used to send messages.
     * We capture these mappings from webhook events where the same message appears with both formats.
     */
    public function up(): void
    {
        Schema::create('lid_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('lid', 50)->unique()->comment('WhatsApp LID (e.g., 81823925276732)');
            $table->string('phone_number', 20)->index()->comment('Real phone number (e.g., 573027038461)');
            $table->string('message_id')->nullable()->comment('Message ID that helped create this mapping');
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            
            $table->index(['lid', 'phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lid_mappings');
    }
};
