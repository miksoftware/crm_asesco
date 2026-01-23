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
                $query->whereRaw('LENGTH(phone_number) < 10')
                    ->orWhere('phone_number', 'like', '%status%')
                    ->orWhere('remote_jid', 'like', '%@broadcast%')
                    ->orWhere('remote_jid', 'like', '%status@%')
                    ->orWhere('remote_jid', 'like', '%@newsletter%')
                    ->orWhere('remote_jid', 'like', '%@g.us%')
                    ->orWhere('remote_jid', 'like', '%@lid%')
                    ->orWhereRaw("phone_number REGEXP '[^0-9]'")
                    ->orWhereRaw("LOWER(push_name) = 'você'")
                    ->orWhereRaw("LOWER(push_name) = 'voce'")
                    ->orWhereRaw("LOWER(name) = 'você'")
                    ->orWhereRaw("LOWER(name) = 'voce'")
                    ->orWhereRaw("push_name REGEXP '^[^a-zA-Z0-9]+$'")
                    ->orWhere('phone_number', 'like', '0%');
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
