<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use Illuminate\Console\Command;

class CleanupInvalidContacts extends Command
{
    protected $signature = 'contacts:cleanup {--channel= : ID del canal específico}';
    protected $description = 'Elimina contactos inválidos (Você, números cortos, broadcasts, etc.)';

    public function handle(): int
    {
        $channelId = $this->option('channel');
        
        if ($channelId) {
            $channels = Channel::where('id', $channelId)->get();
        } else {
            $channels = Channel::all();
        }

        $totalCleaned = 0;

        foreach ($channels as $channel) {
            $this->info("Procesando canal: {$channel->name}");
            
            $cleaned = $this->cleanupChannel($channel->id);
            $totalCleaned += $cleaned;
            
            $this->info("  - Eliminados: {$cleaned} contactos inválidos");
        }

        $this->newLine();
        $this->info("Total eliminados: {$totalCleaned} contactos inválidos");

        return Command::SUCCESS;
    }

    private function cleanupChannel(int $channelId): int
    {
        $invalidContacts = Contact::where('channel_id', $channelId)
            ->where(function ($query) {
                $query
                    // Status broadcasts
                    ->where('remote_jid', 'like', '%@broadcast%')
                    ->orWhere('remote_jid', 'like', '%status@%')
                    // Newsletter
                    ->orWhere('remote_jid', 'like', '%@newsletter%')
                    // Groups
                    ->orWhere('remote_jid', 'like', '%@g.us%')
                    // "Você" status contacts
                    ->orWhereRaw("LOWER(TRIM(push_name)) = 'você'")
                    ->orWhereRaw("LOWER(TRIM(push_name)) = 'voce'")
                    ->orWhereRaw("LOWER(TRIM(name)) = 'você'")
                    ->orWhereRaw("LOWER(TRIM(name)) = 'voce'")
                    // LID contacts that don't have real phone numbers
                    ->orWhereRaw("phone_number NOT REGEXP '^[1-9][0-9]{9,14}$'");
            })
            ->get();

        $count = $invalidContacts->count();

        foreach ($invalidContacts as $contact) {
            $contact->messages()->delete();
            $contact->labelRelations()->detach();
            $contact->delete();
        }

        return $count;
    }
}
