<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactorización de identidad dual en contactos.
 * 
 * Formaliza el esquema para soportar contactos que llegan con LID
 * (Link ID de WhatsApp) sin número real, permitiendo operar con ellos
 * y resolver el número real de forma asíncrona.
 * 
 * Esquema:
 * - phone_number: MSISDN real (puede ser el LID temporalmente)
 * - lid_jid: Identificador LID original (nullable, se llena cuando el contacto llega como LID)
 * - is_lid: Flag que indica si el contacto aún no tiene número real
 * - remote_jid: JID operativo (@s.whatsapp.net o @lid) para enviar mensajes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Índice para búsquedas rápidas de contactos LID por lid_jid
            // Permite encontrar contactos LID para fusionar cuando llega el número real
            $table->index('lid_jid', 'idx_contacts_lid_jid');

            // Índice compuesto para búsqueda de contacto por canal + remote_jid
            // Usado en processIncomingMessage para encontrar contactos por JID
            $table->index(['channel_id', 'remote_jid'], 'idx_contacts_channel_remote_jid');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_lid_jid');
            $table->dropIndex('idx_contacts_channel_remote_jid');
        });
    }
};
