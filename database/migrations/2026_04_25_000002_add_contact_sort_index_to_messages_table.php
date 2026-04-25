<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index to cover the ContactInfo query:
     * WHERE contact_id = ? ORDER BY sent_at ASC, created_at ASC LIMIT 1
     *
     * Without this index MySQL must load all messages for a contact into the
     * sort buffer to resolve the ORDER BY, causing HY001 when the contact has
     * many messages across all channels.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(
                ['contact_id', 'sent_at', 'created_at'],
                'idx_contact_sent_created'
            );
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_contact_sent_created');
        });
    }
};
