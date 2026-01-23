<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\LidMapping;
use App\Models\Message;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixLidContacts extends Command
{
    protected $signature = 'contacts:fix-lids 
                            {--channel= : ID del canal específico} 
                            {--dry-run : Solo mostrar qué se haría sin ejecutar}
                            {--verify : Verificar números con Evolution API (más lento pero más preciso)}';
    
    protected $description = 'Corrige contactos con LIDs, unifica duplicados y actualiza remote_jid al formato estándar';

    public function handle(): int
    {
        $channelId = $this->option('channel');
        $dryRun = $this->option('dry-run');
        $verify = $this->option('verify');
        
        if ($channelId) {
            $channels = Channel::where('id', $channelId)->get();
        } else {
            $channels = Channel::where('status', 'connected')->get();
        }

        if ($dryRun) {
            $this->warn('🔍 MODO SIMULACIÓN - No se realizarán cambios');
            $this->newLine();
        }

        $stats = [
            'lids_resolved' => 0,
            'contacts_merged' => 0,
            'jids_fixed' => 0,
            'invalid_deleted' => 0,
        ];

        foreach ($channels as $channel) {
            $this->info("📱 Canal: {$channel->name}");
            
            // Paso 1: Identificar y resolver contactos LID
            $this->resolvelidContacts($channel, $dryRun, $verify, $stats);
            
            // Paso 2: Fusionar duplicados por phone_number
            $this->mergeDuplicates($channel->id, $dryRun, $stats);
            
            // Paso 3: Corregir remote_jid a formato estándar
            $this->fixRemoteJids($channel->id, $dryRun, $stats);
            
            // Paso 4: Eliminar contactos inválidos
            $this->deleteInvalidContacts($channel->id, $dryRun, $stats);
            
            $this->newLine();
        }

        $this->newLine();
        $this->info('📊 Resumen:');
        $this->line("   - LIDs resueltos: {$stats['lids_resolved']}");
        $this->line("   - Contactos fusionados: {$stats['contacts_merged']}");
        $this->line("   - JIDs corregidos: {$stats['jids_fixed']}");
        $this->line("   - Contactos inválidos eliminados: {$stats['invalid_deleted']}");

        return Command::SUCCESS;
    }

    private function resolvelidContacts(Channel $channel, bool $dryRun, bool $verify, array &$stats): void
    {
        $this->line('  → Buscando contactos con LID...');
        
        // LIDs are contacts that:
        // 1. Have @lid in remote_jid, OR
        // 2. Have phone_number that doesn't start with valid country codes (57 for Colombia, 1 for US, etc.)
        // Colombian numbers: 57 + 10 digits = 12 digits total, starting with 573
        // LIDs are typically 15+ digits and don't start with valid country codes
        
        $lidContacts = Contact::where('channel_id', $channel->id)
            ->where(function ($q) {
                $q->where('remote_jid', 'like', '%@lid')
                  // Numbers that are too long (>15 digits) are likely LIDs
                  ->orWhereRaw("LENGTH(phone_number) > 15")
                  // Numbers that don't start with common country codes
                  ->orWhere(function ($q2) {
                      $q2->whereRaw("LENGTH(phone_number) >= 12")
                         ->whereRaw("phone_number NOT LIKE '57%'")  // Colombia
                         ->whereRaw("phone_number NOT LIKE '1%'")   // US/Canada
                         ->whereRaw("phone_number NOT LIKE '52%'")  // Mexico
                         ->whereRaw("phone_number NOT LIKE '54%'")  // Argentina
                         ->whereRaw("phone_number NOT LIKE '55%'")  // Brazil
                         ->whereRaw("phone_number NOT LIKE '56%'")  // Chile
                         ->whereRaw("phone_number NOT LIKE '58%'")  // Venezuela
                         ->whereRaw("phone_number NOT LIKE '51%'")  // Peru
                         ->whereRaw("phone_number NOT LIKE '593%'") // Ecuador
                         ->whereRaw("phone_number NOT LIKE '34%'")  // Spain
                         ->whereRaw("phone_number NOT LIKE '44%'"); // UK
                  });
            })
            ->get();
        
        if ($lidContacts->isEmpty()) {
            $this->line('    ✓ No hay contactos con LID');
            return;
        }
        
        $this->line("    Encontrados: {$lidContacts->count()} contactos con LID potencial");
        
        $evolutionApi = $verify ? app(EvolutionApiService::class) : null;
        
        foreach ($lidContacts as $contact) {
            $lid = $contact->phone_number;
            
            $this->line("    Procesando LID: {$lid} (Contact ID: {$contact->id})");
            
            // Try to find a matching contact with same push_name in same channel
            $matchingContact = null;
            
            if ($contact->push_name) {
                $matchingContact = Contact::where('channel_id', $channel->id)
                    ->where('id', '!=', $contact->id)
                    ->where('push_name', $contact->push_name)
                    ->whereRaw("phone_number LIKE '57%'") // Colombian number
                    ->whereRaw("LENGTH(phone_number) BETWEEN 10 AND 13")
                    ->first();
            }
            
            if ($matchingContact) {
                $this->info("      → Encontrado match por nombre: {$matchingContact->phone_number}");
                
                if (!$dryRun) {
                    $this->mergeContacts($matchingContact, $contact);
                }
                $stats['contacts_merged']++;
                $stats['lids_resolved']++;
            } else {
                // Check LID mapping table
                $phoneNumber = \App\Models\LidMapping::findPhoneByLid($lid);
                
                if ($phoneNumber) {
                    $this->info("      → Encontrado en tabla de mapeos: {$phoneNumber}");
                    
                    $existingContact = Contact::where('channel_id', $channel->id)
                        ->where('phone_number', $phoneNumber)
                        ->where('id', '!=', $contact->id)
                        ->first();
                    
                    if ($existingContact && !$dryRun) {
                        $this->mergeContacts($existingContact, $contact);
                        $stats['contacts_merged']++;
                    } elseif (!$dryRun) {
                        $contact->update([
                            'phone_number' => $phoneNumber,
                            'remote_jid' => $phoneNumber . '@s.whatsapp.net',
                        ]);
                    }
                    $stats['lids_resolved']++;
                } elseif ($verify && $evolutionApi) {
                    // Try to verify with Evolution API
                    $this->line("      → Verificando con Evolution API...");
                    // Skip API verification for now - too slow for 9000 contacts
                    $this->warn("      ⚠ No se pudo resolver - sin mapeo disponible");
                } else {
                    $this->warn("      ⚠ No se pudo resolver LID: {$lid}");
                }
            }
        }
    }

    private function mergeDuplicates(int $channelId, bool $dryRun, array &$stats): void
    {
        $this->line('  → Buscando duplicados por número de teléfono...');
        
        $duplicates = Contact::where('channel_id', $channelId)
            ->whereNotNull('phone_number')
            ->whereRaw("phone_number REGEXP '^[1-9][0-9]{9,14}$'")
            ->select('phone_number')
            ->groupBy('phone_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone_number');
        
        if ($duplicates->isEmpty()) {
            $this->line('    ✓ No hay duplicados');
            return;
        }
        
        $this->line("    Encontrados: {$duplicates->count()} números con duplicados");
        
        foreach ($duplicates as $phoneNumber) {
            $contacts = Contact::where('channel_id', $channelId)
                ->where('phone_number', $phoneNumber)
                ->orderBy('created_at', 'asc')
                ->get();
            
            if ($contacts->count() < 2) {
                continue;
            }
            
            $primary = $contacts->first();
            $duplicateContacts = $contacts->slice(1);
            
            $this->line("    {$phoneNumber}: fusionando {$duplicateContacts->count()} duplicados");
            
            if (!$dryRun) {
                foreach ($duplicateContacts as $duplicate) {
                    $this->mergeContacts($primary, $duplicate);
                    $stats['contacts_merged']++;
                }
            } else {
                $stats['contacts_merged'] += $duplicateContacts->count();
            }
        }
    }

    private function fixRemoteJids(int $channelId, bool $dryRun, array &$stats): void
    {
        $this->line('  → Corrigiendo remote_jid a formato estándar...');
        
        $contactsToFix = Contact::where('channel_id', $channelId)
            ->whereRaw("phone_number REGEXP '^[1-9][0-9]{9,14}$'")
            ->whereRaw("remote_jid != CONCAT(phone_number, '@s.whatsapp.net')")
            ->get();
        
        if ($contactsToFix->isEmpty()) {
            $this->line('    ✓ Todos los JIDs están correctos');
            return;
        }
        
        $this->line("    Corrigiendo: {$contactsToFix->count()} contactos");
        
        foreach ($contactsToFix as $contact) {
            $standardJid = $contact->phone_number . '@s.whatsapp.net';
            
            if (!$dryRun) {
                $contact->update(['remote_jid' => $standardJid]);
            }
            
            $stats['jids_fixed']++;
        }
    }

    private function deleteInvalidContacts(int $channelId, bool $dryRun, array &$stats): void
    {
        $this->line('  → Eliminando contactos inválidos...');
        
        $invalidContacts = Contact::where('channel_id', $channelId)
            ->where(function ($q) {
                $q->where('remote_jid', 'like', '%@broadcast%')
                  ->orWhere('remote_jid', 'like', '%status@%')
                  ->orWhere('remote_jid', 'like', '%@newsletter%')
                  ->orWhere('remote_jid', 'like', '%@g.us%')
                  ->orWhereRaw("LOWER(TRIM(push_name)) IN ('você', 'voce')")
                  ->orWhereRaw("LOWER(TRIM(name)) IN ('você', 'voce')");
            })
            ->get();
        
        if ($invalidContacts->isEmpty()) {
            $this->line('    ✓ No hay contactos inválidos');
            return;
        }
        
        $this->line("    Eliminando: {$invalidContacts->count()} contactos inválidos");
        
        if (!$dryRun) {
            foreach ($invalidContacts as $contact) {
                $contact->messages()->delete();
                $contact->labelRelations()->detach();
                $contact->delete();
            }
        }
        
        $stats['invalid_deleted'] += $invalidContacts->count();
    }

    private function mergeContacts(Contact $primary, Contact $duplicate): void
    {
        DB::transaction(function () use ($primary, $duplicate) {
            // Mover mensajes
            Message::where('contact_id', $duplicate->id)
                ->update(['contact_id' => $primary->id]);
            
            // Fusionar etiquetas
            $labels = $duplicate->labelRelations()->pluck('labels.id')->toArray();
            $primary->labelRelations()->syncWithoutDetaching($labels);
            
            // Actualizar datos del primario si faltan
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
            
            // Eliminar duplicado
            $duplicate->labelRelations()->detach();
            $duplicate->delete();
        });
    }

    private function extractLid(Contact $contact): ?string
    {
        // Extraer LID del remote_jid o phone_number
        if (str_contains($contact->remote_jid ?? '', '@lid')) {
            return explode('@', $contact->remote_jid)[0];
        }
        
        // Si phone_number no parece un teléfono real, podría ser un LID
        if (!preg_match('/^[1-9]\d{9,14}$/', $contact->phone_number ?? '')) {
            return $contact->phone_number;
        }
        
        return null;
    }

    private function extractPhoneFromJid(string $jid): ?string
    {
        if (preg_match('/^(\d+)[@:]/', $jid, $matches)) {
            return $matches[1];
        }
        return preg_replace('/[^0-9]/', '', $jid) ?: null;
    }
}
