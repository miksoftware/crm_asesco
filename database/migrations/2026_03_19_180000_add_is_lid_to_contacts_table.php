<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega flag is_lid a contactos para identificar leads temporales
 * que llegaron via LID (Link ID) de WhatsApp sin número real.
 * Estos leads provienen típicamente de anuncios Click-to-WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_lid')->default(false)->after('is_group');
            $table->string('lid_jid', 100)->nullable()->after('is_lid');
            $table->index('is_lid', 'idx_contacts_is_lid');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_is_lid');
            $table->dropColumn(['is_lid', 'lid_jid']);
        });
    }
};
