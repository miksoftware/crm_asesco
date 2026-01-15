<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

class UpdateContactsRemoteJid extends Command
{
    protected $signature = 'contacts:update-jid';
    protected $description = 'Update contacts remote_jid from phone_number';

    public function handle(): int
    {
        $this->info('Actualizando remote_jid de contactos...');

        $contacts = Contact::whereNull('remote_jid')->get();
        $updated = 0;

        foreach ($contacts as $contact) {
            // Generate remote_jid from phone_number
            $remoteJid = $contact->phone_number . '@s.whatsapp.net';
            $contact->update(['remote_jid' => $remoteJid]);
            $updated++;
        }

        $this->info("✓ {$updated} contactos actualizados");
        return Command::SUCCESS;
    }
}
