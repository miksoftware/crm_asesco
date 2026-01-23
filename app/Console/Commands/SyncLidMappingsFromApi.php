<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\LidMapping;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLidMappingsFromApi extends Command
{
    protected $signature = 'lid:sync-from-api 
                            {--channel= : ID del canal específico}
                            {--dry-run : Solo mostrar qué se haría}';
    
    protected $description = 'Sincroniza mapeos LID desde Evolution API usando los chats existentes';

    public function handle(EvolutionApiService $evolutionApi): int
    {
        $channelId = $this->option('channel');
        $dryRun = $this->option('dry-run');
        
        if ($channelId) {
            $channels = Channel::where('id', $channelId)->where('status', 'connected')->get();
        } else {
            $channels = Channel::where('status', 'connected')->get();
        }

        if ($dryRun) {
            $this->warn('🔍 MODO SIMULACIÓN');
        }

        $totalMappings = 0;
        $totalMerged = 0;

        foreach ($channels as $channel) {
            $this->info("📱 Canal: {$channel->name} ({$channel->instance_name})");
            
            // Get all chats from Evolution API
            $this->line('  → Obteniendo chats desde Evolution API...');
            $result = $evolutionApi->fetchChats($channel->instance_name);
            
            if (!$result['success']) {
                $this->error("  ✗ Error: " . ($result['error'] ?? 'Unknown'));
                continue;
            }
            
            $chats = $result['data'] ?? [];
            $this->line("  → Encontrados: " . count($chats) . " chats");
            
            // Build a map of LID -> real phone number from chats
            $lidToPhoneMap = [];
            foreach ($chats as $chat) {
                $remoteJid = $chat['remoteJid'] ?? null;
                
                // Skip groups and broadcasts
                if (!$remoteJid || str_contains($remoteJid, '@g.us') || str_contains($remoteJid, '@broadcast')) {
                    continue;
                }
                
                // Check if remoteJid is a LID
                if (str_contains($remoteJid, '@lid')) {
                    $lid = explode('@', $remoteJid)[0];
                    
                    // Look for remoteJidAlt in lastMessage.key
                    $remoteJidAlt = $chat['lastMessage']['key']['remoteJidAlt'] ?? null;
                    
                    if ($remoteJidAlt && str_contains($remoteJidAlt, '@s.whatsapp.net')) {
                        $realPhone = explode('@', $remoteJidAlt)[0];
                        
                        // Validate it's a real Colombian number
                        if (preg_match('/^57[0-9]{10}$/', $realPhone)) {
                            $lidToPhoneMap[$lid] = $realPhone;
                        }
                    }
                }
            }
            
            $this->line("  → Mapeos LID encontrados en API: " . count($lidToPhoneMap));
            
            // Get contacts with LID in this channel
            $lidContacts = Contact::where('channel_id', $channel->id)
                ->where(function ($q) {
                    $q->whereRaw("phone_number NOT REGEXP '^57[0-9]{10}$'")
                      ->whereRaw('LENGTH(phone_number) > 13');
                })
                ->get();
            
            $this->line("  → Contactos LID a resolver: {$lidContacts->count()}");
            
            foreach ($lidContacts as $contact) {
                $lid = $contact->phone_number;
                
                // Check if we have a mapping for this LID
                if (isset($lidToPhoneMap[$lid])) {
                    $realPhone = $lidToPhoneMap[$lid];
                    $this->info("    LID {$lid} → {$realPhone}");
                    
                    if (!$dryRun) {
                        // Create LID mapping
                        LidMapping::updateOrCreate(
                            ['lid' => $lid],
                            ['phone_number' => $realPhone, 'channel_id' => $channel->id]
                        );
                        $totalMappings++;
                        
                        // Check if there's already a contact with this real number
                        $existingContact = Contact::where('channel_id', $channel->id)
                            ->where('phone_number', $realPhone)
                            ->where('id', '!=', $contact->id)
                            ->first();
                        
                        if ($existingContact) {
                            // Merge contacts
                            $this->mergeContacts($existingContact, $contact);
                            $totalMerged++;
                        } else {
                            // Update this contact with real number
                            $contact->update([
                                'phone_number' => $realPhone,
                                'remote_jid' => $realPhone . '@s.whatsapp.net',
                            ]);
                        }
                    } else {
                        $totalMappings++;
                    }
                }
            }
            
            $this->newLine();
        }

        $this->info("📊 Resumen:");
        $this->line("   - Mapeos creados: {$totalMappings}");
        $this->line("   - Contactos fusionados: {$totalMerged}");

        return Command::SUCCESS;
    }

    private function mergeContacts(Contact $primary, Contact $duplicate): void
    {
        DB::transaction(function () use ($primary, $duplicate) {
            // Move messages
            \App\Models\Message::where('contact_id', $duplicate->id)
                ->update(['contact_id' => $primary->id]);
            
            // Merge labels
            $labels = $duplicate->labelRelations()->pluck('labels.id')->toArray();
            if (!empty($labels)) {
                $primary->labelRelations()->syncWithoutDetaching($labels);
            }
            
            // Update primary with best data
            $updates = [];
            if (!$primary->name && $duplicate->name) {
                $updates['name'] = $duplicate->name;
            }
            if (!$primary->push_name && $duplicate->push_name) {
                $updates['push_name'] = $duplicate->push_name;
            }
            if (!empty($updates)) {
                $primary->update($updates);
            }
            
            // Delete duplicate
            $duplicate->labelRelations()->detach();
            $duplicate->delete();
        });
    }
}
