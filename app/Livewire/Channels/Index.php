<?php

namespace App\Livewire\Channels;

use App\Models\Channel;
use App\Services\EvolutionApiService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

#[Layout('layouts.app')]
#[Title('Canales')]
class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public int $perPage = 10;

    public ?string $qrCodeModal = null;
    public ?string $qrCodeImage = null;
    public ?int $qrChannelId = null;

    public bool $canCreate = false;
    public bool $canEdit = false;
    public bool $canDelete = false;

    public function mount(): void
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        
        $this->canCreate = $isAdmin || $user->hasPermission('canales.crear');
        $this->canEdit = $isAdmin || $user->hasPermission('canales.editar');
        $this->canDelete = $isAdmin || $user->hasPermission('canales.eliminar');

        // Sincronizar instancias de Evolution API al cargar
        $this->syncFromEvolutionApi();
    }

    public function syncFromEvolutionApi(): void
    {
        $api = new EvolutionApiService();
        $result = $api->getAllInstances();

        if (!$result['success']) {
            return;
        }

        $instances = $result['data'] ?? [];
        
        foreach ($instances as $instance) {
            $instanceName = trim($instance['name'] ?? '');
            if (empty($instanceName)) continue;

            $connectionStatus = $instance['connectionStatus'] ?? 'close';
            $status = match ($connectionStatus) {
                'open' => 'connected',
                'connecting' => 'connecting',
                default => 'disconnected',
            };

            // Obtener número de ownerJid o number
            $phoneNumber = null;
            if (!empty($instance['ownerJid'])) {
                $phoneNumber = preg_replace('/[^0-9]/', '', explode('@', $instance['ownerJid'])[0]);
            }
            if (empty($phoneNumber) && !empty($instance['number'])) {
                $phoneNumber = preg_replace('/[^0-9]/', '', $instance['number']);
            }

            Channel::updateOrCreate(
                ['instance_name' => $instanceName],
                [
                    'name' => $instance['name'] ?? $instanceName,
                    'phone_number' => $phoneNumber,
                    'token' => $instance['token'] ?? null,
                    'status' => $status,
                    'integration' => $instance['integration'] ?? 'WHATSAPP-BAILEYS',
                ]
            );
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function connect(int $id): void
    {
        $channel = Channel::findOrFail($id);
        $api = new EvolutionApiService();

        try {
            // Intentar conectar y obtener QR
            $result = $api->connectInstance($channel->instance_name);

            if ($result['success']) {
                $data = $result['data'];

                // Buscar QR en diferentes formatos de respuesta
                $qrCode = $data['base64'] ?? $data['qrcode']['base64'] ?? null;

                if ($qrCode) {
                    // Limpiar el prefijo data:image si existe
                    $qrBase64 = $qrCode;
                    if (str_starts_with($qrCode, 'data:image')) {
                        $qrBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $qrCode);
                    }

                    $channel->update([
                        'status' => 'qr_code',
                        'qr_code' => $qrBase64,
                    ]);
                    
                    $this->qrCodeModal = $channel->name;
                    $this->qrCodeImage = $qrBase64;
                    $this->qrChannelId = $channel->id;
                } elseif (isset($data['instance']['state']) && $data['instance']['state'] === 'open') {
                    $this->updateChannelInfo($channel, $api);
                    $this->dispatch('toast', type: 'success', message: 'Canal conectado correctamente');
                } else {
                    // Verificar estado actual
                    $stateResult = $api->getConnectionState($channel->instance_name);
                    if ($stateResult['success'] && ($stateResult['data']['instance']['state'] ?? '') === 'open') {
                        $this->updateChannelInfo($channel, $api);
                        $this->dispatch('toast', type: 'success', message: 'Canal ya está conectado');
                    } else {
                        $channel->update(['status' => 'connecting']);
                        $this->dispatch('toast', type: 'info', message: 'Conectando... presiona de nuevo para ver el QR');
                    }
                }
            } else {
                $errorMsg = $result['error'] ?? 'Error desconocido';
                $this->dispatch('toast', type: 'error', message: 'Error: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error de conexión: ' . $e->getMessage());
        }
    }

    public function refreshQr(): void
    {
        if ($this->qrChannelId) {
            $channel = Channel::find($this->qrChannelId);
            if ($channel) {
                $api = new EvolutionApiService();
                
                // Primero verificar si ya está conectado
                $stateResult = $api->getConnectionState($channel->instance_name);
                if ($stateResult['success'] && ($stateResult['data']['instance']['state'] ?? '') === 'open') {
                    $this->updateChannelInfo($channel, $api);
                    $this->closeQrModal();
                    $this->dispatch('toast', type: 'success', message: '¡Canal conectado exitosamente!');
                    return;
                }
                
                // Si no está conectado, obtener nuevo QR
                $result = $api->connectInstance($channel->instance_name);
                
                if ($result['success']) {
                    $qrCode = $result['data']['base64'] ?? $result['data']['qrcode']['base64'] ?? null;
                    
                    if ($qrCode) {
                        $qrBase64 = $qrCode;
                        if (str_starts_with($qrCode, 'data:image')) {
                            $qrBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $qrCode);
                        }
                        $this->qrCodeImage = $qrBase64;
                        $channel->update(['qr_code' => $qrBase64]);
                    } elseif (isset($result['data']['instance']['state']) && $result['data']['instance']['state'] === 'open') {
                        $this->updateChannelInfo($channel, $api);
                        $this->closeQrModal();
                        $this->dispatch('toast', type: 'success', message: '¡Canal conectado exitosamente!');
                    }
                }
            }
        }
    }

    public function checkConnectionStatus(int $id): void
    {
        $channel = Channel::findOrFail($id);
        $api = new EvolutionApiService();

        $result = $api->getConnectionState($channel->instance_name);

        if ($result['success'] && isset($result['data']['instance']['state'])) {
            $state = $result['data']['instance']['state'];
            
            if ($state === 'open') {
                $this->updateChannelInfo($channel, $api);
                
                if ($this->qrChannelId === $id) {
                    $this->closeQrModal();
                }
                
                $this->dispatch('toast', type: 'success', message: '¡Canal conectado exitosamente!');
            }
        }
    }

    protected function updateChannelInfo(Channel $channel, EvolutionApiService $api): void
    {
        $channel->update(['status' => 'connected', 'qr_code' => null]);
        
        // Intentar obtener el número del perfil
        $instanceInfo = $api->getInstance($channel->instance_name);
        if ($instanceInfo['success'] && !empty($instanceInfo['data'])) {
            $instances = $instanceInfo['data'];
            $instance = is_array($instances) && isset($instances[0]) ? $instances[0] : $instances;
            
            $phone = $instance['ownerJid'] ?? $instance['number'] ?? null;
            if ($phone) {
                $phoneNumber = preg_replace('/[^0-9]/', '', explode('@', $phone)[0]);
                if ($phoneNumber) {
                    $channel->update(['phone_number' => $phoneNumber]);
                }
            }
        }
    }

    public function disconnect(int $id): void
    {
        $channel = Channel::findOrFail($id);
        $api = new EvolutionApiService();

        $api->disconnectInstance($channel->instance_name);

        $channel->update(['status' => 'disconnected', 'qr_code' => null]);
        $this->dispatch('toast', type: 'success', message: 'Canal desconectado');
    }

    public function restartInstance(int $id): void
    {
        $channel = Channel::findOrFail($id);
        $api = new EvolutionApiService();

        $result = $api->restartInstance($channel->instance_name);

        if ($result['success']) {
            $channel->update(['status' => 'connecting', 'qr_code' => null]);
            $this->dispatch('toast', type: 'info', message: 'Instancia reiniciada. Presiona Conectar para obtener nuevo QR.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Error al reiniciar: ' . ($result['error'] ?? 'Error desconocido'));
        }
    }

    public function refreshStatus(int $id): void
    {
        $channel = Channel::findOrFail($id);
        $api = new EvolutionApiService();

        $result = $api->getConnectionState($channel->instance_name);

        if ($result['success'] && isset($result['data']['instance']['state'])) {
            $state = $result['data']['instance']['state'];
            $status = match ($state) {
                'open' => 'connected',
                'connecting' => 'connecting',
                default => 'disconnected',
            };
            
            $channel->update(['status' => $status]);

            if ($status === 'connected') {
                $this->updateChannelInfo($channel, $api);
                $this->dispatch('toast', type: 'success', message: 'Estado actualizado: Conectado');
            } else {
                $this->dispatch('toast', type: 'info', message: 'Estado actualizado: ' . ucfirst($status));
            }
        }
    }

    public function closeQrModal(): void
    {
        $this->qrCodeModal = null;
        $this->qrCodeImage = null;
        $this->qrChannelId = null;
    }

    public function confirmDelete(int $id): void
    {
        if (!$this->canDelete) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para eliminar canales');
            return;
        }
        
        $this->dispatch('confirm-delete', id: $id, message: 'El canal será eliminado permanentemente');
    }

    #[On('deleteConfirmed')]
    public function delete(int $id): void
    {
        if (!$this->canDelete) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para eliminar canales');
            return;
        }

        $channel = Channel::find($id);

        if (!$channel) {
            $this->dispatch('toast', type: 'error', message: 'Canal no encontrado');
            return;
        }

        $api = new EvolutionApiService();
        $api->deleteInstance($channel->instance_name);

        $channel->delete();
        $this->dispatch('toast', type: 'success', message: 'Canal eliminado correctamente');
    }

    public function render()
    {
        $channels = Channel::query()
            ->withCount('users')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('instance_name', 'like', '%' . $this->search . '%')
                      ->orWhere('phone_number', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.channels.index', [
            'channels' => $channels,
        ]);
    }
}
