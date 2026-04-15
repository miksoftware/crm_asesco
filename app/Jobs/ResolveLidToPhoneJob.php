<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\LidMapping;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job de resolución activa de LID.
 * 
 * Capa 3: Cuando el webhook llega solo con @lid y no pudimos resolver
 * el número en el momento, este Job consulta los endpoints REST de
 * Evolution API de forma asíncrona para intentar obtener el número real.
 * 
 * Endpoints que consulta:
 * 1. /chat/findContacts/{instance} — busca el contacto por JID
 * 2. /chat/fetchProfile/{instance} — fuerza a WhatsApp a resolver el perfil
 */
class ResolveLidToPhoneJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60];

    public function __construct(
        private int $contactId,
        private string $lidJid,
        private string $instanceName,
    ) {}

    public function handle(EvolutionApiService $api): void
    {
        $contact = Contact::find($this->contactId);
        if (!$contact) {
            Log::debug('ResolveLidJob: Contacto no encontrado', ['id' => $this->contactId]);
            return;
        }

        // Si ya fue resuelto (por otro webhook o manualmente), salir
        if (!$contact->is_lid && $contact->remote_jid && !str_contains($contact->remote_jid, '@lid')) {
            Log::debug('ResolveLidJob: Contacto ya resuelto', ['id' => $this->contactId]);
            return;
        }

        $lidPart = explode('@', $this->lidJid)[0];
        $lidPart = explode(':', $lidPart)[0];

        // INTENTO 1: Buscar en contactos de Evolution API
        $phone = $this->tryFetchContacts($api, $lidPart);

        // INTENTO 2: Consultar perfil (fuerza resolución en WhatsApp)
        if (!$phone) {
            $phone = $this->tryFetchProfile($api);
        }

        // INTENTO 3: Buscar mensajes del chat para encontrar remoteJidAlt
        if (!$phone) {
            $phone = $this->tryFindMessages($api);
        }

        if ($phone) {
            Log::info('ResolveLidJob: LID resuelto', [
                'contact_id' => $this->contactId,
                'lid' => $lidPart,
                'phone' => $phone,
            ]);

            // Guardar mapeo
            LidMapping::createMapping($lidPart, $phone, null, $contact->channel_id);

            // Resolver el contacto (fusiona si ya existe uno con ese número)
            try {
                $contact->resolveLid($phone);
            } catch (\Exception $e) {
                Log::warning('ResolveLidJob: Error al resolver contacto', [
                    'contact_id' => $this->contactId,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::debug('ResolveLidJob: No se pudo resolver', [
                'contact_id' => $this->contactId,
                'lid' => $lidPart,
                'attempt' => $this->attempts(),
            ]);
        }
    }

    /**
     * Buscar en los contactos de Evolution API por pushName o JID.
     */
    private function tryFetchContacts(EvolutionApiService $api, string $lidPart): ?string
    {
        try {
            $result = $api->fetchContacts($this->instanceName);
            if (!$result['success']) return null;

            $contacts = collect($result['data'] ?? []);

            // Buscar contacto cuyo remoteJid contenga el LID
            // Evolution API a veces almacena el mapeo internamente
            $contact = Contact::find($this->contactId);
            if (!$contact) return null;

            // Buscar por pushName si lo tenemos
            if ($contact->push_name) {
                $matches = $contacts->filter(function ($c) use ($contact) {
                    return !($c['isGroup'] ?? false)
                        && ($c['pushName'] ?? '') === $contact->push_name
                        && str_contains($c['remoteJid'] ?? '', '@s.whatsapp.net');
                });

                if ($matches->count() === 1) {
                    $jid = $matches->first()['remoteJid'];
                    $phone = explode('@', $jid)[0];
                    if (preg_match('/^[1-9]\d{9,14}$/', $phone)) {
                        return $phone;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('ResolveLidJob: Error en fetchContacts', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Consultar perfil del LID en Evolution API.
     * Esto fuerza a WhatsApp a resolver el mapeo interno.
     */
    private function tryFetchProfile(EvolutionApiService $api): ?string
    {
        try {
            $response = app(\Illuminate\Http\Client\Factory::class)
                ->timeout(15)
                ->withHeaders([
                    'apikey' => config('services.evolution.api_key'),
                ])
                ->post(
                    rtrim(config('services.evolution.url'), '/') . "/chat/fetchProfile/{$this->instanceName}",
                    ['number' => $this->lidJid]
                );

            if ($response->successful()) {
                $data = $response->json();
                // Evolution puede devolver el número real en varios campos
                $jid = $data['jid'] ?? $data['wuid'] ?? $data['id'] ?? null;
                if ($jid && str_contains($jid, '@s.whatsapp.net')) {
                    $phone = explode('@', $jid)[0];
                    if (preg_match('/^[1-9]\d{9,14}$/', $phone)) {
                        return $phone;
                    }
                }

                // También verificar campo number
                $number = $data['number'] ?? null;
                if ($number && preg_match('/^[1-9]\d{9,14}$/', $number)) {
                    return $number;
                }
            }
        } catch (\Exception $e) {
            Log::debug('ResolveLidJob: Error en fetchProfile', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Buscar mensajes del chat para encontrar remoteJidAlt.
     */
    private function tryFindMessages(EvolutionApiService $api): ?string
    {
        try {
            $response = app(\Illuminate\Http\Client\Factory::class)
                ->timeout(15)
                ->withHeaders([
                    'apikey' => config('services.evolution.api_key'),
                ])
                ->post(
                    rtrim(config('services.evolution.url'), '/') . "/chat/findMessages/{$this->instanceName}",
                    [
                        'where' => ['key' => ['remoteJid' => $this->lidJid]],
                        'page' => 1,
                        'offset' => 5,
                    ]
                );

            if ($response->successful()) {
                $data = $response->json();
                $messages = $data['messages']['records'] ?? $data['messages'] ?? [];

                foreach ($messages as $msg) {
                    $alt = $msg['key']['remoteJidAlt'] ?? null;
                    if ($alt && str_contains($alt, '@s.whatsapp.net')) {
                        $phone = explode('@', $alt)[0];
                        $phone = explode(':', $phone)[0];
                        if (preg_match('/^[1-9]\d{9,14}$/', $phone)) {
                            return $phone;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('ResolveLidJob: Error en findMessages', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
