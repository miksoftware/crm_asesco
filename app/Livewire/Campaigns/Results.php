<?php

namespace App\Livewire\Campaigns;

use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Resultados de Campaña')]
class Results extends Component
{
    use WithPagination;

    public Campaign $campaign;

    #[Url(history: true)]
    public string $statusFilter = '';

    #[Url(history: true)]
    public string $search = '';

    public int $perPage = 25;

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    #[Computed]
    public function recipients()
    {
        $query = CampaignRecipient::where('campaign_id', $this->campaign->id);

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->search)) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('phone_number', 'like', $searchTerm)
                    ->orWhere('name', 'like', $searchTerm);
            });
        }

        return $query->orderBy('id', 'asc')->paginate($this->perPage);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => $this->campaign->total_recipients,
            'sent' => $this->campaign->sent_count,
            'failed' => $this->campaign->failed_count,
            'pending' => $this->campaign->pending_count,
            'progress' => $this->campaign->progress_percentage,
        ];
    }

    public function startCampaign(): void
    {
        if (!in_array($this->campaign->status, ['draft', 'paused'])) {
            $this->dispatch('toast', type: 'error', message: 'Esta campaña no puede ser iniciada');
            return;
        }

        $this->campaign->update(['status' => 'pending']);
        ProcessCampaignJob::dispatch($this->campaign);
        
        $this->dispatch('toast', type: 'success', message: 'Campaña iniciada');
    }

    public function pauseCampaign(): void
    {
        if ($this->campaign->status !== 'running') {
            $this->dispatch('toast', type: 'error', message: 'Esta campaña no puede ser pausada');
            return;
        }

        $this->campaign->update(['status' => 'paused']);
        $this->dispatch('toast', type: 'success', message: 'Campaña pausada');
    }

    public function cancelCampaign(): void
    {
        if (!in_array($this->campaign->status, ['pending', 'running', 'paused'])) {
            $this->dispatch('toast', type: 'error', message: 'Esta campaña no puede ser cancelada');
            return;
        }

        $this->campaign->update(['status' => 'cancelled']);
        $this->dispatch('toast', type: 'success', message: 'Campaña cancelada');
    }

    public function retryFailed(): void
    {
        if (!in_array($this->campaign->status, ['completed', 'paused', 'cancelled'])) {
            $this->dispatch('toast', type: 'error', message: 'No se pueden reintentar los fallidos en este momento');
            return;
        }

        // Marcar fallidos como pendientes
        $count = CampaignRecipient::where('campaign_id', $this->campaign->id)
            ->whereIn('status', ['failed', 'invalid'])
            ->update([
                'status' => 'pending',
                'error_message' => null,
            ]);

        if ($count === 0) {
            $this->dispatch('toast', type: 'error', message: 'No hay mensajes fallidos para reintentar');
            return;
        }

        // Actualizar contadores
        $this->campaign->update([
            'status' => 'pending',
            'pending_count' => $this->campaign->pending_count + $count,
            'failed_count' => 0,
        ]);

        // Despachar job
        ProcessCampaignJob::dispatch($this->campaign);

        $this->dispatch('toast', type: 'success', message: $count . ' mensajes marcados para reintento');
    }

    public function exportResults(): void
    {
        // TODO: Implementar exportación a CSV
        $this->dispatch('toast', type: 'info', message: 'Función de exportación próximamente');
    }

    public function render()
    {
        return view('livewire.campaigns.results');
    }
}
