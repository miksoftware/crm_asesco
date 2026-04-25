<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index to cover the chat message query:
     * WHERE contact_id = ? AND channel_id = ? ORDER BY sent_at DESC, id DESC
     *
     * Without this index MySQL must load all matching rows into the sort buffer
     * and sort them in memory, which triggers HY001 (Out of sort memory) when
     * a contact has many messages.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(
                ['contact_id', 'channel_id', 'sent_at', 'id'],
                'idx_contact_channel_sent_id'
            );
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_contact_channel_sent_id');
        });
    }
};
