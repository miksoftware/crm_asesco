<?php

namespace App\Console\Commands;

use App\Models\PaymentPromise;
use App\Services\MessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPaymentPromises extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promises:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía automáticamente los mensajes de promesas de pago programados';

    /**
     * Execute the console command.
     */
    public function handle(MessageService $messageService)
    {
        $this->info('Buscando promesas de pago pendientes de enviar...');

        // Buscar promesas que:
        // 1. Estén pendientes (pending)
        // 2. Tengan mensaje definido (notes NOT NULL y diferente a cadena vacía)
        // 3. No se hayan enviado aún (message_sent = false)
        // 4. La fecha y hora programada ya pasó o es ahora (promised_date <= now())
        $promises = PaymentPromise::with(['contact', 'user'])
            ->where('status', 'pending')
            ->where('message_sent', false)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->where('promised_date', '<=', now())
            ->get();

        if ($promises->isEmpty()) {
            $this->info('No hay mensajes de promesa programados para enviar en este momento.');
            return;
        }

        $count = 0;

        foreach ($promises as $promise) {
            $contact = $promise->contact;
            $user = $promise->user;

            if (!$contact || !$contact->phone_number || !$contact->channel_id) {
                Log::warning("Promesa de pago #{$promise->id} omitida por falta de datos del contacto o canal.");
                continue;
            }

            try {
                // Enviar el mensaje usando el texto exacto configurado en "notes"
                $messageService->sendTextMessage(
                    $contact->channel_id,
                    $contact->phone_number,
                    $promise->notes,
                    $contact->is_group,
                    $user ? $user->id : null
                );

                // Marcar como enviado
                $promise->update(['message_sent' => true]);
                $count++;

                $this->info("Mensaje enviado exitosamente para la promesa #{$promise->id} al contacto {$contact->phone_number}.");
            } catch (\Exception $e) {
                Log::error("Error al enviar mensaje de promesa #{$promise->id}: " . $e->getMessage());
                $this->error("Error en la promesa #{$promise->id}: " . $e->getMessage());
            }
        }

        $this->info("Se enviaron {$count} mensajes de promesas de pago.");
    }
}
