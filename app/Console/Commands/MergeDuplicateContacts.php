<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateContacts extends Command
{
    protected $signature = 'contacts:merge-duplicates {--channel= : ID del canal específico} {--dry-run : Solo mostrar qué se haría sin ejecutar}';
    protected $description = 'Fusiona contactos duplicados que tienen el mismo número de teléfono (causados por JIDs @lid)';

    public function handle(): int
    {
        $channelId = $this->option('channel');
        $dryRun = $this->option('dry-run');
        
        if ($channelId) {
            $channels = Channel::where('id', $channelId)->get();
        } else {
            $channels = Channel::all();
        }

        if ($dryRun) {
            $this->warn('MODO SIMULACIÓN - No se realizarán cambios');
            $this->newLine();
        }

        $totalMerged = 0;

        foreach ($channels as $channel) {
            $this->info("Procesando canal: {$channel->name}");
            
            $merged = $this->mergeChannelDuplicates($channel->id, $dryRun);
            $totalMerged += $merged;
            
            $this->info("  - Contactos fusionados: {$merged}");
        }

        $this->newLine();
        $this->info("Total contactos fusionados: {$totalMerged}");

        return Command::SUCCESS;
    }

    private function mergeChannelDuplicates(int $channelId, bool $dryRun): int
    {
        // Find phone numbers that have multiple contacts
        $duplicates = Contact::where('channel_id', $channelId)
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->select('phone_number')
            ->groupBy('phone_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone_number');

        $mergedCount = 0;

        foreach ($duplicates as $phoneNumber) {
            $contacts = Contact::where('channel_id', $channelId)
                ->where('phone_number', $phoneNumber)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($contacts->count() < 2) {
                continue;
            }

            // Keep the first contact (oldest) as the primary
            $primaryContact = $contacts->first();
            $duplicateContacts = $contacts->slice(1);

            $this->line("    Número: {$phoneNumber}");
            $this->line("      - Principal: ID {$primaryContact->id} (JID: {$primaryContact->remote_jid})");

            foreach ($duplicateContacts as $duplicate) {
                $this->line("      - Duplicado: ID {$duplicate->id} (JID: {$duplicate->remote_jid})");
                
                if (!$dryRun) {
                    $this->mergeTwoContacts($primaryContact, $duplicate);
                }
            }

            $mergedCount += $duplicateContacts->count();
        }

        // Also fix contacts with @lid JIDs that don't have duplicates
        $lidContacts = Contact::where('channel_id', $channelId)
            ->where('remote_jid', 'like', '%@lid')
            ->get();

        foreach ($lidContacts as $contact) {
            $phoneNumber = $this->extractPhoneNumber($contact->remote_jid);
            if ($phoneNumber) {
                $standardJid = $phoneNumber . '@s.whatsapp.net';
                
                if ($contact->remote_jid !== $standardJid) {
                    $this->line("    Corrigiendo JID: {$contact->remote_jid} -> {$standardJid}");
                    
                    if (!$dryRun) {
                        $contact->update([
                            'remote_jid' => $standardJid,
                            'phone_number' => $phoneNumber,
                        ]);
                    }
                }
            }
        }

        return $mergedCount;
    }

    private function mergeTwoContacts(Contact $primary, Contact $duplicate): void
    {
        DB::transaction(function () use ($primary, $duplicate) {
            // Move all messages from duplicate to primary
            Message::where('contact_id', $duplicate->id)
                ->update(['contact_id' => $primary->id]);

            // Merge labels (add any labels from duplicate that primary doesn't have)
            $duplicateLabels = $duplicate->labelRelations()->pluck('labels.id')->toArray();
            $primary->labelRelations()->syncWithoutDetaching($duplicateLabels);

            // Update primary contact with best available data
            $updates = [];
            
            // Prefer name from duplicate if primary doesn't have one
            if (!$primary->name && $duplicate->name) {
                $updates['name'] = $duplicate->name;
            }
            
            // Prefer push_name from duplicate if primary doesn't have one
            if (!$primary->push_name && $duplicate->push_name) {
                $updates['push_name'] = $duplicate->push_name;
            }

            // Ensure remote_jid is in standard format
            $phoneNumber = $primary->phone_number ?: $this->extractPhoneNumber($primary->remote_jid);
            if ($phoneNumber) {
                $updates['remote_jid'] = $phoneNumber . '@s.whatsapp.net';
                $updates['phone_number'] = $phoneNumber;
            }

            if (!empty($updates)) {
                $primary->update($updates);
            }

            // Delete duplicate's labels pivot
            $duplicate->labelRelations()->detach();
            
            // Delete the duplicate contact
            $duplicate->delete();
        });
    }

    private function extractPhoneNumber(string $remoteJid): ?string
    {
        // Format: 573001234567@s.whatsapp.net or 573001234567:123@lid
        if (preg_match('/^(\d+)[@:]/', $remoteJid, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
