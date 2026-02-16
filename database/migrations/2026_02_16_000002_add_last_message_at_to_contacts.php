<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Denormalize last_message_at on contacts table.
     * This eliminates the expensive correlated subquery for sorting conversations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('last_message_at')->nullable()->after('metadata');
            $table->index('last_message_at', 'idx_contacts_last_message_at');
            $table->index(['channel_id', 'last_message_at'], 'idx_contacts_channel_last_msg');
            $table->index(['channel_id', 'is_group', 'last_message_at'], 'idx_contacts_channel_group_last_msg');
        });

        // Backfill existing data from messages
        DB::statement('
            UPDATE contacts c 
            SET last_message_at = (
                SELECT MAX(sent_at) 
                FROM messages m 
                WHERE m.contact_id = c.id
            )
        ');
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_last_message_at');
            $table->dropIndex('idx_contacts_channel_last_msg');
            $table->dropIndex('idx_contacts_channel_group_last_msg');
            $table->dropColumn('last_message_at');
        });
    }
};
