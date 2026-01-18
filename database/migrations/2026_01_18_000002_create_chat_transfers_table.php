<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_channel_id')->constrained('channels')->onDelete('cascade');
            $table->foreignId('to_channel_id')->constrained('channels')->onDelete('cascade');
            $table->foreignId('from_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('to_user_id')->constrained('users')->onDelete('cascade');
            $table->text('internal_note')->nullable(); // Internal message visible only to agents
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
        });

        // Add assigned_user_id to contacts for tracking who owns the conversation
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('channel_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn('assigned_user_id');
        });
        Schema::dropIfExists('chat_transfers');
    }
};
