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
        $data = array_merge([
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
            'rejectCall' => false,
            'groupsIgnore' => false,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => false,
            'syncFullHistory' => false,
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
