<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class BulkMessageService
{
    public function __construct(
        private EvolutionApiService $evolutionApi
    ) {}

    /**
     * Envía un mensaje a un destinatario de campaña.
     * Retorna true si se envió correctamente, false si falló.
     */
    public function sendToRecipient(Campaign $campaign, CampaignRecipient $recipient): bool
    {
        $channel = $campaign->channel;
        
        if (!$channel || $channel->status !== 'connected') {
            $recipient->update([
                'status' => 'failed',
                'error_message' => 'Canal no conectado',
            ]);
            return false;
        }

        // Preparar el mensaje: usar el del recipient si tiene uno propio, sino el de la campaña con placeholders
        if (!empty($recipient->message_content)) {
            $message = $recipient->message_content;
        } else {
            $message = $this->renderMessage($campaign->message_content, [
                'nombre' => $recipient->name ?? '',
                'val1' => $recipient->val1 ?? '',
                'val2' => $recipient->val2 ?? '',
            ]);
        }

        try {
            // Normalizar número de teléfono
            $phoneNumber = $this->normalizePhoneNumber($recipient->phone_number);
            
            // Verificar si el número es válido (opcional, puede desactivarse para velocidad)
            // $checkResult = $this->evolutionApi->checkWhatsAppNumber($channel->instance_name, $phoneNumber);
            // if (!$checkResult['exists']) {
            //     $recipient->update([
            //         'status' => 'invalid',
            //         'error_message' => 'Número no registrado en WhatsApp',
            //     ]);
            //     return false;
            // }

            // Enviar mensaje
            $response = $this->evolutionApi->sendTextMessage(
                $channel->instance_name,
                $phoneNumber,
                $message
            );

            if ($response['success']) {
                $messageId = $response['data']['key']['id'] ?? null;
                
                // Guardar mensaje en la base de datos local
                $this->saveMessageToDatabase($channel, $phoneNumber, $message, $messageId, $campaign->user_id);
                
                $recipient->update([
                    'status' => 'sent',
                    'message_id' => $messageId,
                    'sent_at' => now(),
                ]);
                
                Log::info('Bulk message sent', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'phone' => $phoneNumber,
                ]);
                
                return true;
            } else {
                $errorMsg = $this->parseErrorMessage($response);
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $errorMsg,
                ]);

                Log::warning('Bulk message failed', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'phone' => $phoneNumber,
                    'status_code' => $response['status'] ?? 0,
                    'raw_error' => $response['error'] ?? '',
                    'parsed_error' => $errorMsg,
                ]);
                
                return false;
            }
        } catch (\Exception $e) {
            $errorMsg = $this->parseExceptionMessage($e->getMessage());
            $recipient->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);
            
            Log::error('Bulk message exception', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'exception' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Parsea el mensaje de error de la API y lo convierte en un mensaje amigable.
     */
    private function parseErrorMessage(array $response): string
    {
        $rawError = $response['error'] ?? '';
        $statusCode = $response['status'] ?? 0;

        // Convertir a string si es array u objeto
        if (is_array($rawError)) {
            $rawError = json_encode($rawError);
        }
        $rawErrorLower = strtolower((string) $rawError);

        // Detectar si el número no existe en WhatsApp
        if (str_contains($rawError, '"exists":false') || str_contains($rawError, 'exists":false') || str_contains($rawErrorLower, 'not registered')) {
            return 'El número no está registrado en WhatsApp';
        }

        // Detectar errores de número inválido
        if (str_contains($rawErrorLower, 'invalid') || str_contains($rawErrorLower, 'inválido') || str_contains($rawErrorLower, 'bad number')) {
            return 'Número de teléfono inválido';
        }

        // Detectar errores de conexión / instancia desconectada
        if (str_contains($rawErrorLower, 'not connected') || str_contains($rawErrorLower, 'disconnected') || str_contains($rawErrorLower, 'closed') || str_contains($rawErrorLower, 'unauthorized')) {
            return 'Canal desconectado — reconectar el canal e intentar de nuevo';
        }

        // Detectar errores de rate limit / spam score
        if (str_contains($rawErrorLower, 'rate') || str_contains($rawErrorLower, 'too many') || $statusCode === 429) {
            return 'Límite de envío alcanzado — aumentar el delay entre mensajes';
        }

        // Detectar errores de bloqueo / spam
        if (str_contains($rawErrorLower, 'blocked') || str_contains($rawErrorLower, 'spam') || str_contains($rawErrorLower, 'ban')) {
            return 'Número bloqueado o marcado como spam por WhatsApp';
        }

        // Detectar errores de timeout
        if (str_contains($rawErrorLower, 'timeout') || str_contains($rawErrorLower, 'timed out')) {
            return 'Tiempo de espera agotado (Evolution API no respondió)';
        }

        // Detectar errores de instancia no encontrada
        if (str_contains($rawErrorLower, 'instance not found') || str_contains($rawErrorLower, 'no instance')) {
            return 'Instancia de WhatsApp no encontrada en Evolution API';
        }

        // Intentar extraer mensaje útil del JSON de error
        $decoded = json_decode($rawError, true);
        if (is_array($decoded)) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? $decoded['response']['message'] ?? null;
            if ($msg) {
                if (is_array($msg)) {
                    $msg = implode('; ', array_map(fn($m) => is_array($m) ? json_encode($m) : $m, $msg));
                }
                $msg = (string) $msg;
                return strlen($msg) > 200 ? substr($msg, 0, 200) . '...' : $msg;
            }
        }

        // Error genérico con código de estado — incluir detalles reales
        if ($statusCode >= 400 && $statusCode < 500) {
            $detail = $rawError ? ': ' . (strlen($rawError) > 150 ? substr($rawError, 0, 150) . '...' : $rawError) : '';
            return 'Error en la solicitud (código ' . $statusCode . ')' . $detail;
        }

        if ($statusCode >= 500) {
            return 'Error del servidor de WhatsApp (código ' . $statusCode . ')';
        }

        // Si no se puede identificar, mostrar error real pero limitado
        if ($rawError) {
            return strlen($rawError) > 200 ? substr($rawError, 0, 200) . '...' : $rawError;
        }

        return 'Error desconocido al enviar';
    }

    /**
     * Parsea excepciones y las convierte en mensajes amigables.
     */
    private function parseExceptionMessage(string $message): string
    {
        if (str_contains($message, 'Connection refused')) {
            return 'No se pudo conectar al servidor de WhatsApp';
        }
        
        if (str_contains($message, 'timed out') || str_contains($message, 'Timeout')) {
            return 'Tiempo de espera agotado';
        }
        
        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'Error de conexión segura';
        }
        
        // Limitar longitud del mensaje
        if (strlen($message) > 100) {
            return substr($message, 0, 100) . '...';
        }
        
        return $message ?: 'Error inesperado';
    }

    /**
     * Reemplaza los placeholders en el mensaje.
     */
    public function renderMessage(string $template, array $data): string
    {
        $message = $template;
        
        foreach ($data as $key => $value) {
            $message = str_replace('{' . $key . '}', $value ?? '', $message);
        }
        
        // Limpiar placeholders no reemplazados
        $message = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $message);
        
        return trim($message);
    }

    /**
     * Normaliza el número de teléfono al formato internacional esperado por Evolution API.
     * Soporta números colombianos (móvil y fijo) y formatos internacionales.
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Eliminar todo lo que no sea dígito
        $normalized = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Quitar ceros iniciales redundantes (ej: 0573001234567 → 573001234567)
        $normalized = ltrim($normalized, '0');

        // Si ya tiene código de país completo (Colombia 57 + 10 dígitos = 12 dígitos)
        if (strlen($normalized) === 12 && str_starts_with($normalized, '57')) {
            return $normalized;
        }

        // Número colombiano local de 10 dígitos (ej: 3001234567) → agregar 57
        if (strlen($normalized) === 10 && (str_starts_with($normalized, '3') || str_starts_with($normalized, '6'))) {
            return '57' . $normalized;
        }

        // Número con 57 + 9 dígitos (error tipográfico, no válido, dejar pasar para que la API lo rechace con mensaje claro)
        // Números de otros países (más de 12 dígitos o prefijo diferente a 57) → dejar como están
        return $normalized;
    }

    /**
     * Guarda el mensaje enviado en la base de datos local.
     */
    private function saveMessageToDatabase(Channel $channel, string $phoneNumber, string $message, ?string $messageId, ?int $userId): void
    {
        try {
            $remoteJid = $phoneNumber . '@s.whatsapp.net';
            
            // Buscar contacto por phone_number (consistente con MessageService y Chat)
            $contact = Contact::where('channel_id', $channel->id)
                ->where('phone_number', $phoneNumber)
                ->first();

            if (!$contact) {
                $contact = Contact::create([
                    'channel_id' => $channel->id,
                    'phone_number' => $phoneNumber,
                    'remote_jid' => $remoteJid,
                    'name' => null,
                    'push_name' => null,
                    'labels' => [],
                    'metadata' => [],
                ]);
            } elseif (!$contact->remote_jid) {
                // Si el contacto existe pero no tiene remote_jid, actualizarlo
                $contact->update(['remote_jid' => $remoteJid]);
            }

            // Crear el mensaje
            Message::create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'message_id' => $messageId ?? ('bulk_' . uniqid()),
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => $message,
                'status' => 'sent',
                'is_read' => true,
                'user_id' => $userId,
                'sent_at' => now(),
            ]);

            // Actualizar última actividad del contacto
            $contact->touch();
            
        } catch (\Exception $e) {
            Log::warning('Failed to save bulk message to database', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calcula el delay aleatorio entre mensajes.
     */
    public function getRandomDelay(int $min, int $max): int
    {
        return rand($min, $max);
    }

    /**
     * Actualiza los contadores de la campaña.
     */
    public function updateCampaignCounts(Campaign $campaign): void
    {
        $campaign->update([
            'sent_count' => $campaign->recipients()->where('status', 'sent')->count(),
            'failed_count' => $campaign->recipients()->whereIn('status', ['failed', 'invalid'])->count(),
            'pending_count' => $campaign->recipients()->where('status', 'pending')->count(),
        ]);
    }

    /**
     * Parsea un archivo Excel (.xlsx/.xls) para campaña Excel.
     * Solo lee teléfono y mensaje por fila.
     */
    public function parseExcelFile(string $filePath): array
    {
        $recipients = [];

        if (!file_exists($filePath)) {
            return [];
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return [];
        }

        // Primera fila como header
        $header = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', (string) ($h ?? ''))));
        }, array_shift($rows));

        if (empty($header) || (count($header) === 1 && empty($header[0]))) {
            return [];
        }

        foreach ($rows as $row) {
            if (empty($row) || (count($row) === 1 && empty(trim((string) ($row[0] ?? ''))))) continue;

            // Ajustar tamaño al header
            if (count($row) > count($header)) {
                $row = array_slice($row, 0, count($header));
            } elseif (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            $data = array_combine($header, $row);

            // Buscar columna de teléfono
            $phone = $data['telefono'] ?? $data['phone'] ?? $data['numero'] ?? $data['celular'] ?? $data['teléfono'] ?? null;
            if (!$phone) {
                foreach ($data as $k => $v) {
                    if (str_contains($k, 'tel') || str_contains($k, 'cel') || str_contains($k, 'num') || str_contains($k, 'phone')) {
                        $phone = $v;
                        break;
                    }
                }
            }

            if (!$phone) continue;

            // Limpiar teléfono (puede venir como float desde Excel)
            $phone = preg_replace('/[^0-9]/', '', (string) intval($phone));
            if (strlen($phone) < 10) continue;

            // Buscar columna de mensaje
            $msg = $data['mensaje'] ?? $data['message'] ?? $data['texto'] ?? $data['msg'] ?? null;
            if (!$msg) {
                foreach ($data as $k => $v) {
                    if (str_contains($k, 'mensaj') || str_contains($k, 'messag') || str_contains($k, 'texto') || str_contains($k, 'msg')) {
                        $msg = $v;
                        break;
                    }
                }
            }

            if (!$msg || empty(trim((string) $msg))) continue;

            $recipients[] = [
                'phone_number' => trim($phone),
                'message_content' => trim((string) $msg),
            ];
        }

        return $recipients;
    }

    /**
     * Parsea un archivo CSV y retorna los destinatarios.
     */
    public function parseCsvFile(string $filePath): array
    {
        $recipients = [];
        
        if (!file_exists($filePath)) {
            return [];
        }

        // Enable auto-detect line endings for older Mac Excel files
        $originalAutoDetect = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', '1');

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            ini_set('auto_detect_line_endings', $originalAutoDetect);
            return [];
        }
        
        // Detectar separador basándose en la primera línea
        $separator = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        
        // Remove BOM from first line
        $lines[0] = preg_replace('/^[\xef\xbb\xbf]+/', '', $lines[0]);
        
        $header = str_getcsv(array_shift($lines), $separator);
        
        if (empty($header) || (count($header) === 1 && empty(trim($header[0])))) {
            ini_set('auto_detect_line_endings', $originalAutoDetect);
            return [];
        }

        // Normalizar headers
        $header = array_map(function ($h) {
            // Eliminar espacios y caracteres invisibles (como NBSP)
            return strtolower(trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $h)));
        }, $header);
        
        foreach ($lines as $line) {
            $row = str_getcsv($line, $separator);
            if (empty($row) || (count($row) === 1 && empty(trim($row[0])))) continue;
            
            // Pad or slice to match header count
            if (count($row) > count($header)) {
                $row = array_slice($row, 0, count($header));
            } else if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }
            
            $data = array_combine($header, $row);
            
            // Buscar columna de teléfono (incluso con nombres ligeramente diferentes)
            $phone = $data['telefono'] ?? $data['phone'] ?? $data['numero'] ?? $data['celular'] ?? null;
            
            // Si el header tiene el nombre pero con otros caracteres que se filtraron, intentar busqueda difusa
            if (!$phone) {
                foreach ($data as $k => $v) {
                    if (str_contains($k, 'tel') || str_contains($k, 'cel') || str_contains($k, 'num') || str_contains($k, 'phone')) {
                        $phone = $v;
                        break;
                    }
                }
            }

            if (!$phone) continue;
            
            // Buscar nombre y variables difusamente si es necesario
            $name = $data['nombre'] ?? $data['name'] ?? null;
            if (!$name) {
                foreach ($data as $k => $v) {
                    if (str_contains($k, 'nom') || str_contains($k, 'name')) {
                        $name = $v;
                        break;
                    }
                }
            }

            $recipients[] = [
                'phone_number' => trim($phone),
                'name' => $name ? trim($name) : null,
                'val1' => isset($data['val1']) ? trim($data['val1']) : (isset($data['variable1']) ? trim($data['variable1']) : null),
                'val2' => isset($data['val2']) ? trim($data['val2']) : (isset($data['variable2']) ? trim($data['variable2']) : null),
            ];
        }
        
        ini_set('auto_detect_line_endings', $originalAutoDetect);
        return $recipients;
    }
}
