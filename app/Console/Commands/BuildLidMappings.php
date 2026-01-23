<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\LidMapping;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildLidMappings extends Command
{
    protected $signature = 'lid:build-mappings {--dry-run : Solo mostrar lo que se haría sin ejecutar}';
    protected $description = 'Construye mapeos LID a partir de mensajes existentes que tienen el mismo message_id con diferentes JIDs';

    public function handle(): int
    {
        $this->info('Buscando mapeos LID en mensajes existentes...');
        
        $dryRun = $this->option('dry-run');
        $mappingsCreated = 0;
        
        // Buscar contactos que parecen ser LIDs (números que no parecen teléfonos reales)
        $potentialLidContacts = Contact::whereRaw("phone_number NOT REGEXP '^[1-9][0-9]{9,14}$'")
            ->orWhere('remote_jid', 'like', '%@lid')
            ->get();
        
        $this->info("Encontrados {$potentialLidContacts->count()} contactos potencialmente LID");
        
        foreach ($potentialLidContacts as $contact) {
            $this->line("  - {$contact->phone_number} (remote_jid: {$contact->remote_jid})");
        }
        
        // Buscar mensajes duplicados por message_id que podrían tener diferentes JIDs
        // Esto requiere analizar los metadatos de los mensajes
        $messagesWithMetadata = Message::whereNotNull('metadata')
            ->where('metadata', '!=', '[]')
            ->where('metadata', '!=', '{}')
            ->get();
        
        $this->info("Analizando {$messagesWithMetadata->count()} mensajes con metadata...");
        
        $jidsByMessageId = [];
        
        foreach ($messagesWithMetadata as $message) {
            $metadata = $message->metadata;
            if (!is_array($metadata)) {
                continue;
            }
            
            $messageId = $metadata['key']['id'] ?? $message->message_id;
            $remoteJid = $metadata['key']['remoteJid'] ?? null;
            
            if ($messageId && $remoteJid) {
                if (!isset($jidsByMessageId[$messageId])) {
                    $jidsByMessageId[$messageId] = [];
                }
                $jidsByMessageId[$messageId][] = $remoteJid;
            }
        }
        
        // Buscar message_ids que tienen múltiples JIDs diferentes
        foreach ($jidsByMessageId as $messageId => $jids) {
            $uniqueJids = array_unique($jids);
            if (count($uniqueJids) > 1) {
                $this->info("Message ID {$messageId} tiene múltiples JIDs:");
                
                $phones = [];
                $lids = [];
                
                foreach ($uniqueJids as $jid) {
                    $jidPart = explode('@', $jid)[0];
                    $cleanPart = explode(':', $jidPart)[0];
                    $isPhone = preg_match('/^[1-9]\d{9,14}$/', $cleanPart);
                    
                    $this->line("    - {$jid} (" . ($isPhone ? 'TELÉFONO' : 'LID') . ")");
                    
                    if ($isPhone) {
                        $phones[] = $cleanPart;
                    } else {
                        $lids[] = $cleanPart;
                    }
                }
                
                // Si tenemos tanto teléfonos como LIDs, crear mapeos
                if (!empty($phones) && !empty($lids)) {
                    $phone = $phones[0]; // Usar el primer teléfono encontrado
                    
                    foreach ($lids as $lid) {
                        if ($dryRun) {
                            $this->warn("    [DRY-RUN] Crearía mapeo: LID {$lid} → Teléfono {$phone}");
                        } else {
                            LidMapping::createMapping($lid, $phone, $messageId);
                            $this->info("    ✓ Mapeo creado: LID {$lid} → Teléfono {$phone}");
                        }
                        $mappingsCreated++;
                    }
                }
            }
        }
        
        $this->newLine();
        
        if ($dryRun) {
            $this->info("Se crearían {$mappingsCreated} mapeos (dry-run)");
        } else {
            $this->info("Se crearon {$mappingsCreated} mapeos LID");
        }
        
        // Mostrar mapeos existentes
        $existingMappings = LidMapping::all();
        if ($existingMappings->isNotEmpty()) {
            $this->newLine();
            $this->info("Mapeos LID existentes:");
            foreach ($existingMappings as $mapping) {
                $this->line("  LID {$mapping->lid} → Teléfono {$mapping->phone_number}");
            }
        }
        
        return Command::SUCCESS;
    }
}
