<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index for unread message count queries.
     * This dramatically speeds up the frequent polling queries that count
     * unread messages per channel.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Index for unread count queries: WHERE channel_id IN (...) AND direction = 'incoming' AND is_read = false
            $table->index(['channel_id', 'direction', 'is_read'], 'idx_channel_direction_read');
            
            // Index for duplicate message check: WHERE message_id = ? AND channel_id = ?
            $table->index(['message_id', 'channel_id'], 'idx_message_channel');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_channel_direction_read');
            $table->dropIndex('idx_message_channel');
        });
    }
};
