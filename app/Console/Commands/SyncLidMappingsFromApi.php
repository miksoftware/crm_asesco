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
            
            // Build a map of remoteJid -> chat info
            $chatMap = [];
            foreach ($chats as $chat) {
                $remoteJid = $chat['remoteJid'] ?? $chat['id'] ?? null;
                if ($remoteJid && !str_contains($remoteJid, '@g.us') && !str_contains($remoteJid, '@broadcast')) {
                    $chatMap[$remoteJid] = $chat;
                }
            }
            
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
                
                // Try to find this LID in the chat map
                $lidJid = $lid . '@lid';
                $lidJidAlt = $lid . '@s.whatsapp.net';
                
                $matchedChat = $chatMap[$lidJid] ?? $chatMap[$lidJidAlt] ?? null;
                
                // Also search by name in chats
                if (!$matchedChat && $contact->push_name) {
                    foreach ($chatMap as $jid => $chat) {
                        $chatName = $chat['name'] ?? $chat['pushName'] ?? null;
                        if ($chatName && $chatName === $contact->push_name) {
                            // Check if this JID is a real phone number
                            $jidPart = explode('@', $jid)[0];
                            if (preg_match('/^57[0-9]{10}$/', $jidPart)) {
                                $matchedChat = $chat;
                                $matchedChat['_matched_jid'] = $jid;
                                break;
                            }
                        }
                    }
                }
                
                if ($matchedChat) {
                    $realJid = $matchedChat['_matched_jid'] ?? $matchedChat['remoteJid'] ?? null;
                    $realPhone = $realJid ? explode('@', $realJid)[0] : null;
                    
                    // Validate it's a real Colombian number
                    if ($realPhone && preg_match('/^57[0-9]{10}$/', $realPhone)) {
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
                        }
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
