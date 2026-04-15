<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Campañas')]
class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $statusFilter = '';

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public int $perPage = 10;

    // Permission flags
    public bool $canCreate = false;
    public bool $canDelete = false;

    public function mount(): void
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');

        $this->canCreate = $isAdmin || $user->hasPermission('campanas.crear');
        $this->canDelete = $isAdmin || $user->hasPermission('campanas.eliminar');
    }

    #[Computed]
    public function campaigns()
    {
        $query = Campaign::with(['channel', 'user']);

        // Filtro de búsqueda (nombre campaña, canal o usuario)
        if (!empty($this->search)) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhereHas('channel', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('user', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', $searchTerm);
                    });
            });
        }

        // Filtro de estado
        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function deleteCampaign(int $id): void
    {
        if (!$this->canDelete) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para eliminar campañas');
            return;
        }

        $campaign = Campaign::find($id);
        
        if (!$campaign) {
            $this->dispatch('toast', type: 'error', message: 'Campaña no encontrada');
            return;
        }

        // No permitir eliminar campañas en ejecución
        if ($campaign->status === 'running') {
            $this->dispatch('toast', type: 'error', message: 'No puedes eliminar una campaña en ejecución');
            return;
        }

        $campaign->delete();
        $this->dispatch('toast', type: 'success', message: 'Campaña eliminada correctamente');
    }

    public function cancelCampaign(int $id): void
    {
        $campaign = Campaign::find($id);
        
        if (!$campaign) {
            $this->dispatch('toast', type: 'error', message: 'Campaña no encontrada');
            return;
        }

        if (!in_array($campaign->status, ['pending', 'running', 'paused'])) {
            $this->dispatch('toast', type: 'error', message: 'Esta campaña no puede ser cancelada');
            return;
        }

        $campaign->update(['status' => 'cancelled']);
        $this->dispatch('toast', type: 'success', message: 'Campaña cancelada');
    }

    public function pauseCampaign(int $id): void
    {
        $campaign = Campaign::find($id);
        
        if (!$campaign || $campaign->status !== 'running') {
            $this->dispatch('toast', type: 'error', message: 'Esta campaña no puede ser pausada');
            return;
        }

        $campaign->update(['status' => 'paused']);
        $this->dispatch('toast', type: 'success', message: 'Campaña pausada');
    }

    public function resumeCampaign(int $id): void
    {
        $campaign = Campaign::find($id);
        
        if (!$campaign || $campaign->status !== 'paused') {
            $this->dispatch('toast', type: 'error', message: 'Esta campaña no puede ser reanudada');
            return;
        }

        $campaign->update(['status' => 'pending']);
        
        // Despachar job para continuar
        \App\Jobs\ProcessCampaignJob::dispatch($campaign);
        
        $this->dispatch('toast', type: 'success', message: 'Campaña reanudada');
    }

    #[On('campaign-created')]
    public function refreshList(): void
    {
        unset($this->campaigns);
    }

    public function render()
    {
        return view('livewire.campaigns.index');
    }
}
