<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Servicio de normalización y resolución de LIDs de WhatsApp.
 * 
 * Capa 2: Intercepta el payload del webhook y aplica un algoritmo
 * determinista para extraer el número real cuando el remoteJid es un LID.
 * 
 * Prioridades de resolución:
 * 1. remoteJidAlt (Evolution API inyecta el número real aquí)
 * 2. senderPn (campo alternativo en algunas versiones de Baileys)
 * 3. participant con formato @s.whatsapp.net
 * 4. Tabla lid_mappings (mapeos previos)
 * 5. Si todo falla, se opera con el LID directamente
 */
class LidResolverService
{
    /**
     * Resultado de la resolución de un JID.
     */
    public function resolve(array $webhookData): LidResolutionResult
    {
        $data = $webhookData['data'] ?? [];
        $key = $data['key'] ?? [];
        $remoteJid = $key['remoteJid'] ?? '';
        $instanceName = $webhookData['instance'] ?? null;

        // Si no es un LID y el número extraído parece un teléfono real, no hace
        // falta resolver nada.
        if (!$this->isLidJid($remoteJid)) {
            $phone = $this->extractPhoneFromJid($remoteJid);
            if (\App\Models\Contact::isValidPhoneFormat($phone)) {
                return new LidResolutionResult(
                    resolved: true,
                    phoneNumber: $phone,
                    jid: $remoteJid,
                    lidIdentifier: null,
                    method: 'direct'
                );
            }

            // WhatsApp a veces entrega un LID disfrazado de número normal
            // (sin "@lid" literal en el JID). Tratarlo igual que un LID explícito
            // y aplicar el mismo algoritmo de resolución.
            $lidPart = $phone;
        } else {
            $lidPart = $this->extractLidPart($remoteJid);
        }

        // PRIORIDAD 1: remoteJidAlt
        $phone = $this->tryRemoteJidAlt($key);
        if ($phone) {
            $this->saveLidMapping($lidPart, $phone, $key['id'] ?? null, $instanceName);
            return new LidResolutionResult(
                resolved: true,
                phoneNumber: $phone,
                jid: $phone . '@s.whatsapp.net',
                lidIdentifier: $lidPart,
                method: 'remoteJidAlt'
            );
        }

        // PRIORIDAD 2: senderPn (campo de Baileys en algunas versiones)
        $phone = $this->trySenderPn($data);
        if ($phone) {
            $this->saveLidMapping($lidPart, $phone, $key['id'] ?? null, $instanceName);
            return new LidResolutionResult(
                resolved: true,
                phoneNumber: $phone,
                jid: $phone . '@s.whatsapp.net',
                lidIdentifier: $lidPart,
                method: 'senderPn'
            );
        }

        // PRIORIDAD 3: participant con @s.whatsapp.net (en algunos payloads)
        $phone = $this->tryParticipant($key);
        if ($phone) {
            $this->saveLidMapping($lidPart, $phone, $key['id'] ?? null, $instanceName);
            return new LidResolutionResult(
                resolved: true,
                phoneNumber: $phone,
                jid: $phone . '@s.whatsapp.net',
                lidIdentifier: $lidPart,
                method: 'participant'
            );
        }

        // PRIORIDAD 4: Tabla lid_mappings
        $phone = \App\Models\LidMapping::findPhoneByLid($lidPart);
        if ($phone) {
            return new LidResolutionResult(
                resolved: true,
                phoneNumber: $phone,
                jid: $phone . '@s.whatsapp.net',
                lidIdentifier: $lidPart,
                method: 'lid_mapping_table'
            );
        }

        // PRIORIDAD 5: Número colombiano válido en el propio LID (raro pero posible)
        $cleanLid = preg_replace('/[^0-9]/', '', explode(':', $lidPart)[0]);
        if (preg_match('/^57\d{10}$/', $cleanLid)) {
            return new LidResolutionResult(
                resolved: true,
                phoneNumber: $cleanLid,
                jid: $cleanLid . '@s.whatsapp.net',
                lidIdentifier: $lidPart,
                method: 'lid_is_phone'
            );
        }

        // No se pudo resolver — retornar con el LID para operar directamente
        Log::debug('LID no resuelto, se operará con LID directamente', [
            'lid' => $lidPart,
            'remoteJid' => $remoteJid,
            'pushName' => $data['pushName'] ?? null,
        ]);

        return new LidResolutionResult(
            resolved: false,
            phoneNumber: null,
            jid: $remoteJid,
            lidIdentifier: $lidPart,
            method: 'unresolved'
        );
    }

    /**
     * Verificar si un JID es formato LID.
     */
    public function isLidJid(string $jid): bool
    {
        return str_contains($jid, '@lid');
    }

    /**
     * Extraer la parte numérica del LID.
     */
    private function extractLidPart(string $jid): string
    {
        $part = explode('@', $jid)[0];
        return explode(':', $part)[0];
    }

    /**
     * Extraer número de teléfono de un JID estándar.
     */
    private function extractPhoneFromJid(string $jid): string
    {
        $part = explode('@', $jid)[0];
        return explode(':', $part)[0];
    }

    /**
     * PRIORIDAD 1: Intentar extraer número de remoteJidAlt.
     */
    private function tryRemoteJidAlt(array $key): ?string
    {
        $alt = $key['remoteJidAlt'] ?? null;
        if (!$alt) return null;

        $cleaned = $this->cleanJidToPhone($alt);
        return $this->isValidPhone($cleaned) ? $cleaned : null;
    }

    /**
     * PRIORIDAD 2: Intentar extraer número de senderPn.
     */
    private function trySenderPn(array $data): ?string
    {
        $senderPn = $data['key']['senderPn'] ?? $data['senderPn'] ?? null;
        if (!$senderPn) return null;

        $cleaned = preg_replace('/[^0-9]/', '', $senderPn);
        return $this->isValidPhone($cleaned) ? $cleaned : null;
    }

    /**
     * PRIORIDAD 3: Intentar extraer número de participant.
     */
    private function tryParticipant(array $key): ?string
    {
        $participant = $key['participant'] ?? null;
        if (!$participant || !str_contains($participant, '@s.whatsapp.net')) return null;

        $cleaned = $this->cleanJidToPhone($participant);
        return $this->isValidPhone($cleaned) ? $cleaned : null;
    }

    /**
     * Limpiar un JID a solo el número de teléfono.
     */
    private function cleanJidToPhone(string $jid): string
    {
        // Remover @s.whatsapp.net, @lid, espacios, saltos de línea
        $cleaned = preg_replace('/[@\s\r\n].*$/', '', $jid);
        // Remover sufijo :XX
        $cleaned = explode(':', $cleaned)[0];
        // Solo dígitos
        return preg_replace('/[^0-9]/', '', $cleaned);
    }

    /**
     * Validar que un string sea un número de teléfono válido.
     */
    private function isValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^[1-9]\d{9,14}$/', $phone);
    }

    /**
     * Guardar mapeo LID → teléfono.
     */
    private function saveLidMapping(string $lid, string $phone, ?string $messageId, ?string $instanceName): void
    {
        try {
            $channelId = null;
            if ($instanceName) {
                $channel = \App\Models\Channel::where('instance_name', $instanceName)->first();
                $channelId = $channel?->id;
            }
            \App\Models\LidMapping::createMapping($lid, $phone, $messageId, $channelId);
        } catch (\Exception $e) {
            Log::warning('Error guardando LID mapping', ['error' => $e->getMessage()]);
        }
    }
}
