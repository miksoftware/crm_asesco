<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

/**
 * Marca contactos existentes que tienen JIDs @lid como is_lid=true.
 * Útil para migrar contactos LID creados antes de implementar el flag.
 */
class MarkLidContacts extends Command
{
    protected $signature = 'contacts:mark-lids {--dry-run : Solo mostrar sin modificar}';
    protected $description = 'Marca contactos con JID @lid como leads temporales (is_lid=true)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODO SIMULACIÓN - No se realizarán cambios');
        }

        // Buscar contactos individuales cuyo remote_jid contiene @lid
        // o que no tienen @s.whatsapp.net ni @g.us (LIDs sin marcar)
        $lidContacts = Contact::where(function ($q) {
                $q->where('is_group', false)->orWhereNull('is_group');
            })
            ->where(function ($q) {
                $q->where('is_lid', false)->orWhereNull('is_lid');
            })
            ->where(function ($q) {
                $q->where('remote_jid', 'like', '%@lid%')
                  ->orWhere(function ($q2) {
                      $q2->where('remote_jid', 'not like', '%@s.whatsapp.net')
                         ->where('remote_jid', 'not like', '%@g.us')
                         ->whereNotNull('remote_jid');
                  });
            })
            ->get();

        if ($lidContacts->isEmpty()) {
            $this->info('No se encontraron contactos LID sin marcar.');
            return self::SUCCESS;
        }

        $this->info("Encontrados: {$lidContacts->count()} contactos LID sin marcar");

        $marked = 0;
        foreach ($lidContacts as $contact) {
            $lidValue = explode('@', $contact->remote_jid ?? '')[0];
            $lidValue = explode(':', $lidValue)[0];

            $this->line("  → {$contact->phone_number} | {$contact->push_name} | JID: {$contact->remote_jid}");

            if (!$dryRun) {
                $contact->update([
                    'is_lid' => true,
                    'lid_jid' => $lidValue ?: $contact->phone_number,
                ]);
                $marked++;
            }
        }

        $this->info("Contactos marcados como LID: " . ($dryRun ? "{$lidContacts->count()} (simulación)" : $marked));

        return self::SUCCESS;
    }
}
