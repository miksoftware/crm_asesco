<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

/**
 * Corrige contactos que tienen "Você"/"Voce" como nombre,
 * descargando el nombre real desde WhatsApp via Evolution API.
 */
class FixVoceContacts extends Command
{
    protected $signature = 'contacts:fix-voce {--channel= : ID del canal específico}';
    protected $description = 'Corregir contactos con nombre "Você" descargando nombres reales desde WhatsApp';

    public function handle(): int
    {
        $query = Contact::where(function ($q) {
            $q->whereRaw("LOWER(TRIM(push_name)) IN ('você', 'voce')")
              ->orWhereRaw("LOWER(TRIM(name)) IN ('você', 'voce')");
        })->where(function ($q) {
            $q->where('is_group', false)->orWhereNull('is_group');
        });

        if ($channelId = $this->option('channel')) {
            $query->where('channel_id', $channelId);
        }

        $voceContacts = $query->get();

        if ($voceContacts->isEmpty()) {
            $this->info('No se encontraron contactos con "Você". Todo limpio.');
            return self::SUCCESS;
        }

        $this->info("Encontrados {$voceContacts->count()} contactos con \"Você\".");

        $api = app(EvolutionApiService::class);
        $fixed = 0;
        $cleared = 0;

        // Agrupar por canal para hacer una sola llamada a fetchContacts por canal
        $byChannel = $voceContacts->groupBy('channel_id');

        foreach ($byChannel as $channelId => $contacts) {
            $channel = Channel::find($channelId);
            if (!$channel) {
                continue;
            }

            $this->line("Canal: {$channel->name} ({$channel->instance_name})");

            // Descargar contactos reales desde WhatsApp
            $result = $api->fetchContacts($channel->instance_name);
            $waContacts = [];

            if ($result['success'] && !empty($result['data'])) {
                foreach ($result['data'] as $waContact) {
                    $remoteJid = $waContact['remoteJid'] ?? $waContact['id'] ?? null;
                    if (!$remoteJid) continue;

                    $jidPart = explode('@', $remoteJid)[0];
                    $phonePart = explode(':', $jidPart)[0];
                    $pushName = $waContact['pushName'] ?? null;

                    if ($pushName && preg_match('/^[1-9]\d{9,14}$/', $phonePart)) {
                        $waContacts[$phonePart] = $pushName;
                    }
                }
            }

            foreach ($contacts as $contact) {
                $realName = $waContacts[$contact->phone_number] ?? null;

                if ($realName) {
                    $contact->update([
                        'push_name' => $realName,
                        'name' => $contact->name && strtolower(trim($contact->name)) !== 'você' && strtolower(trim($contact->name)) !== 'voce'
                            ? $contact->name
                            : $realName,
                    ]);
                    $this->line("  ✓ {$contact->phone_number}: Você → {$realName}");
                    $fixed++;
                } else {
                    // No encontramos nombre real, al menos limpiar "Você"
                    $updates = [];
                    if (in_array(strtolower(trim($contact->push_name ?? '')), ['você', 'voce'])) {
                        $updates['push_name'] = null;
                    }
                    if (in_array(strtolower(trim($contact->name ?? '')), ['você', 'voce'])) {
                        $updates['name'] = null;
                    }
                    if (!empty($updates)) {
                        $contact->update($updates);
                        $this->line("  ~ {$contact->phone_number}: Você eliminado (nombre real no disponible)");
                        $cleared++;
                    }
                }
            }
        }

        $this->info("Resultado: {$fixed} corregidos, {$cleared} limpiados.");
        return self::SUCCESS;
    }
}
