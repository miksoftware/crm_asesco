<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Channel;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResolveLidContacts extends Command
{
    protected $signature = 'contacts:resolve-lids {--dry-run : Solo mostrar sin modificar}';
    protected $description = 'Resolver contactos LID usando los contactos de Evolution API';

    public function handle(EvolutionApiService $api): int
    {
        $isDryRun = $this->option('dry-run');
        
        // Obtener todos los contactos LID individuales con mensajes
        $lidContacts = Contact::where(function ($q) {
                $q->where('is_lid', true)
                  ->orWhere('remote_jid', 'like', '%@lid');
            })
            ->where(function ($q) {
                $q->where('is_group', false)->orWhereNull('is_group');
            })
            ->get();

        $this->info("Contactos LID encontrados: {$lidContacts->count()}");

        if ($lidContacts->isEmpty()) {
            $this->info('No hay contactos LID para resolver.');
            return self::SUCCESS;
        }

        // Agrupar por canal
        $byChannel = $lidContacts->groupBy('channel_id');
        $resolved = 0;
        $merged = 0;
        $notFound = 0;

        foreach ($byChannel as $channelId => $contacts) {
            $channel = Channel::find($channelId);
            if (!$channel) continue;

            $this->info("\n--- Canal: {$channel->name} (ID: {$channelId}) - {$contacts->count()} LIDs ---");

            // Obtener contactos de Evolution API para este canal
            $result = $api->fetchContacts($channel->instance_name);
            if (!$result['success']) {
                $this->warn("  No se pudieron obtener contactos de Evolution API: " . ($result['error'] ?? 'unknown'));
                continue;
            }

            $evoContacts = collect($result['data'] ?? []);
            $this->info("  Contactos en Evolution API: {$evoContacts->count()}");

            // Crear mapa de pushName -> número real para intentar cruzar
            $evoByPhone = $evoContacts->filter(fn($c) => !($c['isGroup'] ?? false))
                ->keyBy(function ($c) {
                    $jid = $c['remoteJid'] ?? '';
                    return explode('@', $jid)[0];
                });

            $evoByPushName = $evoContacts->filter(fn($c) => !($c['isGroup'] ?? false) && !empty($c['pushName']))
                ->groupBy('pushName');

            foreach ($contacts as $lidContact) {
                $realPhone = null;
                $matchMethod = null;

                // Método 1: Buscar por push_name exacto
                if ($lidContact->push_name && $evoByPushName->has($lidContact->push_name)) {
                    $matches = $evoByPushName->get($lidContact->push_name);
                    if ($matches->count() === 1) {
                        $phone = explode('@', $matches->first()['remoteJid'])[0];
                        if (preg_match('/^\d{10,15}$/', $phone)) {
                            $realPhone = $phone;
                            $matchMethod = 'pushName exacto';
                        }
                    }
                }

                // Método 2: Buscar por nombre
                if (!$realPhone && $lidContact->name) {
                    $nameMatches = $evoContacts->filter(function ($c) use ($lidContact) {
                        return !($c['isGroup'] ?? false) 
                            && ($c['pushName'] ?? '') === $lidContact->name;
                    });
                    if ($nameMatches->count() === 1) {
                        $phone = explode('@', $nameMatches->first()['remoteJid'])[0];
                        if (preg_match('/^\d{10,15}$/', $phone)) {
                            $realPhone = $phone;
                            $matchMethod = 'nombre exacto';
                        }
                    }
                }

                if ($realPhone) {
                    // Verificar si ya existe un contacto con ese número en el mismo canal
                    $existing = Contact::where('channel_id', $channelId)
                        ->where('phone_number', $realPhone)
                        ->where('id', '!=', $lidContact->id)
                        ->first();

                    if ($existing) {
                        $this->line("  ✅ LID #{$lidContact->id} ({$lidContact->phone_number}) -> {$realPhone} [{$matchMethod}] -> FUSIONAR con #{$existing->id}");
                        if (!$isDryRun) {
                            // Mover mensajes al contacto existente
                            $lidContact->messages()->update(['contact_id' => $existing->id]);
                            $lidContact->labelRelations()->detach();
                            // Actualizar last_message_at
                            $latestMsg = $existing->messages()->max('sent_at');
                            if ($latestMsg) {
                                $existing->update(['last_message_at' => $latestMsg]);
                            }
                            $lidContact->delete();
                            $merged++;
                        }
                    } else {
                        $this->line("  ✅ LID #{$lidContact->id} ({$lidContact->phone_number}) -> {$realPhone} [{$matchMethod}]");
                        if (!$isDryRun) {
                            $lidContact->update([
                                'phone_number' => $realPhone,
                                'remote_jid' => $realPhone . '@s.whatsapp.net',
                                'is_lid' => false,
                                'lid_jid' => $lidContact->phone_number,
                            ]);
                            // Crear LID mapping
                            \App\Models\LidMapping::createMapping($lidContact->phone_number, $realPhone, null, $channelId);
                            $resolved++;
                        }
                    }
                } else {
                    $this->line("  ❌ LID #{$lidContact->id} ({$lidContact->phone_number}) - {$lidContact->push_name} -> No se pudo resolver");
                    $notFound++;
                }
            }
        }

        $this->info("\n=== Resumen ===");
        $this->info("Resueltos: {$resolved}");
        $this->info("Fusionados: {$merged}");
        $this->info("No resueltos: {$notFound}");
        
        if ($isDryRun) {
            $this->warn("(Modo dry-run - no se hicieron cambios)");
        }

        return self::SUCCESS;
    }
}
