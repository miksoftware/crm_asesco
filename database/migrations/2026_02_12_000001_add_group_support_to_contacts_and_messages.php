<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('remote_jid');
            $table->string('group_jid', 100)->nullable()->after('is_group');
            
            $table->index('is_group', 'idx_is_group');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('sender_name', 100)->nullable()->after('media_mime_type');
            $table->string('sender_phone', 30)->nullable()->after('sender_name');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_is_group');
            $table->dropColumn(['is_group', 'group_jid']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['sender_name', 'sender_phone']);
        });
    }
};
