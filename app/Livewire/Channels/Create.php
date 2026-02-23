<?php

namespace App\Livewire\Channels;

use App\Models\Channel;
use App\Models\User;
use App\Services\EvolutionApiService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Rule;

#[Layout('layouts.app')]
#[Title('Crear Canal')]
class Create extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|string|max:255|unique:channels,instance_name|alpha_dash')]
    public string $instance_name = '';

    public array $selectedUsers = [];

    public function save(): void
    {
        $this->validate();

        $api = new EvolutionApiService();

        // Crear instancia en Evolution API (webhook se configura automáticamente)
        $result = $api->createInstance($this->instance_name);

        if (!$result['success']) {
            $this->dispatch('toast', type: 'error', message: 'Error al crear instancia: ' . ($result['error'] ?? 'Error desconocido'));
            return;
        }

        $token = $result['data']['hash'] ?? $result['data']['token'] ?? null;

        $channel = Channel::create([
            'name' => $this->name,
            'instance_name' => $this->instance_name,
            'token' => $token,
            'integration' => 'WHATSAPP-BAILEYS',
            'status' => 'disconnected',
        ]);

        if (!empty($this->selectedUsers)) {
            $channel->users()->sync($this->selectedUsers);
        }

        // Configurar webhook por separado como respaldo
        // (ya se configura en createInstance, pero por si acaso)
        try {
            $api->setWebhook($this->instance_name);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error setting webhook as backup', [
                'instance' => $this->instance_name,
                'error' => $e->getMessage(),
            ]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'Canal creado correctamente']);
        $this->redirectRoute('channels.index');
    }

    public function render()
    {
        return view('livewire.channels.create', [
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
