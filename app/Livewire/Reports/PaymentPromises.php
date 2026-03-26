<?php

namespace App\Livewire\Reports;

use App\Models\PaymentPromise;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;

#[Layout('layouts.app')]
#[Title('Promesas de Pago')]
class PaymentPromises extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public ?int $userId = null;

    #[Url]
    public string $status = 'all'; // all, pending, sent

    #[Url]
    public string $dateFilterType = 'created_at'; // created_at, promised_date

    // Modal properties
    public bool $showModal = false;
    public ?PaymentPromise $selectedPromise = null;

    public function mount(): void
    {
        if (empty($this->dateFrom)) {
            $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        }
        if (empty($this->dateTo)) {
            $this->dateTo = now()->format('Y-m-d');
        }

        // Si no es admin, forzamos su ID y no mostramos selector de agente
        if (!auth()->user()->hasRole('admin')) {
            $this->userId = auth()->id();
        }
    }

    #[Computed]
    public function users(): Collection
    {
        if (auth()->user()->hasRole('admin')) {
            return User::orderBy('name')->get();
        }
        return collect([auth()->user()]);
    }

    public function openModal(int $promiseId)
    {
        $promise = PaymentPromise::with(['contact', 'user'])->find($promiseId);
        
        // Security check
        if ($promise && (auth()->user()->hasRole('admin') || $promise->user_id === auth()->id())) {
            $this->selectedPromise = $promise;
            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedPromise = null;
    }

    public function resetFilters(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->status = 'all';
        $this->dateFilterType = 'created_at';
        
        if (auth()->user()->hasRole('admin')) {
            $this->userId = null;
        } else {
            $this->userId = auth()->id();
        }
        
        $this->resetPage();
    }

    public function updated($property): void
    {
        if (in_array($property, ['dateFrom', 'dateTo', 'userId', 'status', 'dateFilterType'])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function promises()
    {
        $query = PaymentPromise::with(['contact.channel', 'user'])
            ->whereBetween($this->dateFilterType, [
                $this->dateFrom . ' 00:00:00',
                $this->dateTo . ' 23:59:59'
            ]);

        // Role-based or explicit filtering
        if (!auth()->user()->hasRole('admin')) {
            $query->where('user_id', auth()->id());
        } elseif ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        // Status filtering
        if ($this->status === 'sent') {
            $query->where('message_sent', true);
        } elseif ($this->status === 'pending') {
            $query->where('message_sent', false);
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    public function render()
    {
        return view('livewire.reports.payment-promises');
    }
}
