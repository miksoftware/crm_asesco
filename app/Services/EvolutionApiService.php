<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class EvolutionApiService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.evolution.url', 'http://localhost:8080'), '/');
        $this->apiKey = config('services.evolution.api_key', '');
    }

    protected function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30);
    }

    public function createInstance(string $instanceName, array $options = []): array
    {
        // Build webhook URL for automatic configuration at creation time
        $webhookUrl = rtrim(config('app.url'), '/') . '/api/webhook/evolution';

        $data = array_merge([
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
            'rejectCall' => false,
            'groupsIgnore' => true,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => false,
            'syncFullHistory' => false,
            'webhook' => [
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => true,
                'headers' => [],
                'events' => [
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                    'MESSAGES_DELETE',
                    'SEND_MESSAGE',
                    'CONNECTION_UPDATE',
                    'QRCODE_UPDATED',
                ],
            ],
        ], $options);

        $response = $this->request()->post('/instance/create', $data);

        return $this->handleResponse($response);
    }

    public function deleteInstance(string $instanceName): array
    {
        $response = $this->request()->delete("/instance/delete/{$instanceName}");

        return $this->handleResponse($response);
    }

    public function getInstance(string $instanceName): array
    {
        $response = $this->request()->get("/instance/fetchInstances", [
            'instanceName' => $instanceName,
        ]);

        return $this->handleResponse($response);
    }

    public function getAllInstances(): array
    {
        $response = $this->request()->get('/instance/fetchInstances');

        return $this->handleResponse($response);
    }

    public function getConnectionState(string $instanceName): array
    {
        $response = $this->request()->get("/instance/connectionState/{$instanceName}");

        return $this->handleResponse($response);
    }

    public function connectInstance(string $instanceName): array
    {
        $response = $this->request()->get("/instance/connect/{$instanceName}");

        return $this->handleResponse($response);
    }

    public function disconnectInstance(string $instanceName): array
    {
        $response = $this->request()->delete("/instance/logout/{$instanceName}");

        return $this->handleResponse($response);
    }

    public function restartInstance(string $instanceName): array
    {
        // Evolution API: POST /instance/restart/{instance}
        $response = $this->request()->post("/instance/restart/{$instanceName}");

        return $this->handleResponse($response);
    }

    public function getQrCode(string $instanceName): array
    {
        $response = $this->request()->get("/instance/connect/{$instanceName}");

        return $this->handleResponse($response);
    }

    public function sendTextMessage(string $instanceName, string $number, string $text): array
    {
        $response = $this->request()->post("/message/sendText/{$instanceName}", [
            'number' => $number,
            'text' => $text,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Send an image message via Evolution API.
     */
    public function sendImageMessage(string $instanceName, string $number, string $imageBase64, ?string $caption = null, string $mimetype = 'image/jpeg'): array
    {
        $response = $this->request()->post("/message/sendMedia/{$instanceName}", [
            'number' => $number,
            'mediatype' => 'image',
            'mimetype' => $mimetype,
            'caption' => $caption,
            'media' => $imageBase64,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Send a document/file message via Evolution API.
     */
    public function sendDocumentMessage(string $instanceName, string $number, string $fileBase64, string $fileName, string $mimetype, ?string $caption = null): array
    {
        $response = $this->request()->post("/message/sendMedia/{$instanceName}", [
            'number' => $number,
            'mediatype' => 'document',
            'mimetype' => $mimetype,
            'caption' => $caption,
            'media' => $fileBase64,
            'fileName' => $fileName,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Send an audio message via Evolution API.
     */
    public function sendAudioMessage(string $instanceName, string $number, string $audioBase64, string $mimetype = 'audio/ogg; codecs=opus'): array
    {
        $response = $this->request()->post("/message/sendWhatsAppAudio/{$instanceName}", [
            'number' => $number,
            'audio' => $audioBase64,
            'encoding' => true,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Send a video message via Evolution API.
     */
    public function sendVideoMessage(string $instanceName, string $number, string $videoBase64, ?string $caption = null, string $mimetype = 'video/mp4'): array
    {
        $response = $this->request()->post("/message/sendMedia/{$instanceName}", [
            'number' => $number,
            'mediatype' => 'video',
            'mimetype' => $mimetype,
            'caption' => $caption,
            'media' => $videoBase64,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Configure webhook for an instance to receive messages.
     * Compatible with Evolution API v2.3.x
     */
    public function setWebhook(string $instanceName, ?string $webhookUrl = null): array
    {
        $url = $webhookUrl ?? rtrim(config('app.url'), '/') . '/api/webhook/evolution';

        $events = [
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONNECTION_UPDATE',
            'QRCODE_UPDATED',
            'CONTACTS_UPSERT',
            'CONTACTS_UPDATE',
            'CHATS_UPSERT',
            'CHATS_UPDATE',
            'MESSAGES_SET',
        ];

        // Evolution API v2.3: formato con propiedad "webhook" en el nivel raíz
        $response = $this->request()->post("/webhook/set/{$instanceName}", [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'byEvents' => false,
                'base64' => true,
                'headers' => (object) [],
                'events' => $events,
            ],
        ]);

        // Fallback: Evolution API v2.x formato alternativo (endpoint /instance/update)
        if (!$response->successful()) {
            $response = $this->request()->put("/instance/update/{$instanceName}", [
                'webhook' => [
                    'enabled' => true,
                    'url' => $url,
                    'byEvents' => false,
                    'base64' => true,
                    'headers' => (object) [],
                    'events' => $events,
                ],
            ]);
        }

        return $this->handleResponse($response);
    }

    /**
     * Get current webhook configuration for an instance.
     */
    public function getWebhook(string $instanceName): array
    {
        $response = $this->request()->get("/webhook/find/{$instanceName}");

        return $this->handleResponse($response);
    }

    /**
     * Update instance settings (syncFullHistory, readMessages, etc.)
     */
    public function setSettings(string $instanceName, array $settings = []): array
    {
        $data = array_merge([
            'rejectCall' => false,
            'groupsIgnore' => false,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => false,
            'syncFullHistory' => true,
        ], $settings);

        $response = $this->request()->post("/settings/set/{$instanceName}", $data);

        return $this->handleResponse($response);
    }

    /**
     * Get current instance settings.
     */
    public function getSettings(string $instanceName): array
    {
        $response = $this->request()->get("/settings/find/{$instanceName}");

        return $this->handleResponse($response);
    }

    /**
     * Fetch chat messages from Evolution API for an instance.
     */
    public function fetchMessages(string $instanceName, string $remoteJid, int $count = 50): array
    {
        // Evolution API v2: POST /chat/findMessages/{instance}
        $response = $this->request()->post("/chat/findMessages/{$instanceName}", [
            'where' => [
                'key' => [
                    'remoteJid' => $remoteJid,
                ],
            ],
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Fetch all chats/contacts from Evolution API for an instance.
     */
    public function fetchChats(string $instanceName): array
    {
        // Evolution API v2: POST /chat/findChats/{instance}
        $response = $this->request()->post("/chat/findChats/{$instanceName}", []);

        return $this->handleResponse($response);
    }

    /**
     * Fetch contacts from Evolution API for an instance.
     */
    public function fetchContacts(string $instanceName): array
    {
        // Evolution API v2: POST /chat/findContacts/{instance}
        $response = $this->request()->post("/chat/findContacts/{$instanceName}", []);

        return $this->handleResponse($response);
    }

    /**
     * Fetch messages from Evolution API - all messages without filter.
     */
    public function fetchAllMessages(string $instanceName, int $page = 1, int $limit = 100): array
    {
        // Evolution API v2: POST /chat/findMessages/{instance}
        $response = $this->request()->post("/chat/findMessages/{$instanceName}", [
            'page' => $page,
            'offset' => $limit,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Get media (image, audio, video, document) as base64 from Evolution API.
     */
    public function getMediaBase64(string $instanceName, string $messageId, string $remoteJid): array
    {
        // Evolution API v2: only message.key.id is required
        $response = $this->request()
            ->timeout(60)
            ->post("/chat/getBase64FromMediaMessage/{$instanceName}", [
                'message' => [
                    'key' => [
                        'id' => $messageId,
                    ],
                ],
                'convertToMp4' => false,
            ]);

        return $this->handleResponse($response);
    }

    /**
     * Check if a phone number is registered on WhatsApp.
     * Returns the JID if exists, null if not.
     */
    public function checkWhatsAppNumber(string $instanceName, string $phoneNumber): array
    {
        // Normalize phone number (remove non-numeric characters)
        $normalizedNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        $response = $this->request()->post("/chat/whatsappNumbers/{$instanceName}", [
            'numbers' => [$normalizedNumber],
        ]);

        $result = $this->handleResponse($response);
        
        if ($result['success'] && !empty($result['data'])) {
            $numbers = $result['data'];
            
            // Check if the number exists in WhatsApp
            foreach ($numbers as $number) {
                if (isset($number['exists']) && $number['exists'] === true) {
                    return [
                        'success' => true,
                        'exists' => true,
                        'jid' => $number['jid'] ?? null,
                        'number' => $number['number'] ?? $normalizedNumber,
                    ];
                }
            }
            
            return [
                'success' => true,
                'exists' => false,
                'number' => $normalizedNumber,
            ];
        }

        return [
            'success' => false,
            'exists' => false,
            'error' => $result['error'] ?? 'Error al verificar número',
        ];
    }

    /**
     * Fetch all groups the instance is part of, including their real names (subject).
     */
    public function fetchAllGroups(string $instanceName): array
    {
        $response = $this->request()->get("/group/fetchAllGroups/{$instanceName}", [
            'getParticipants' => 'false',
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Fetch metadata for a specific group (name, description, participants, etc).
     */
    public function findGroupInfo(string $instanceName, string $groupJid): array
    {
        $response = $this->request()->get("/group/findGroupInfos/{$instanceName}", [
            'groupJid' => $groupJid,
        ]);

        return $this->handleResponse($response);
    }

    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'error' => $response->json('message') ?? $response->body(),
            'status' => $response->status(),
        ];
    }
}
