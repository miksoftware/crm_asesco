<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * These indexes significantly improve query performance for chat filtering.
     */
    public function up(): void
    {
        // Indexes for messages table
        Schema::table('messages', function (Blueprint $table) {
            // Composite index for unread messages query
            $table->index(['channel_id', 'direction', 'is_read'], 'idx_messages_channel_unread');
            
            // Composite index for contact messages with date
            $table->index(['contact_id', 'sent_at'], 'idx_messages_contact_sent');
            
            // Index for direction filtering
            $table->index(['direction'], 'idx_messages_direction');
            
            // Index for is_read filtering
            $table->index(['is_read'], 'idx_messages_is_read');
        });

        // Indexes for contacts table
        Schema::table('contacts', function (Blueprint $table) {
            // Index for assigned user filtering
            $table->index(['assigned_user_id'], 'idx_contacts_assigned_user');
            
            // Composite index for channel + assigned user
            $table->index(['channel_id', 'assigned_user_id'], 'idx_contacts_channel_assigned');
        });

        // Indexes for contact_label pivot table
        Schema::table('contact_label', function (Blueprint $table) {
            // Index for label filtering
            $table->index(['label_id'], 'idx_contact_label_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_channel_unread');
            $table->dropIndex('idx_messages_contact_sent');
            $table->dropIndex('idx_messages_direction');
            $table->dropIndex('idx_messages_is_read');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_assigned_user');
            $table->dropIndex('idx_contacts_channel_assigned');
        });

        Schema::table('contact_label', function (Blueprint $table) {
            $table->dropIndex('idx_contact_label_label');
        });
    }
};
