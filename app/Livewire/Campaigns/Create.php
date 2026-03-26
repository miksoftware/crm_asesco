<?php

namespace App\Livewire\Campaigns;

use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignTemplate;
use App\Models\Channel;
use App\Services\BulkMessageService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Nueva Campaña')]
class Create extends Component
{
    use WithFileUploads;

    // Step 1: Configuración básica
    #[Rule('required|min:3|max:100')]
    public string $name = '';

    #[Rule('required|exists:channels,id')]
    public ?int $channelId = null;

    // Step 2: Mensaje
    #[Rule('nullable|exists:campaign_templates,id')]
    public ?int $templateId = null;

    #[Rule('required|min:1|max:4096')]
    public string $messageContent = '';

    // Step 3: Destinatarios
    public $csvFile = null;
    public array $recipients = [];
    public string $manualNumbers = '';

    // Step 4: Configuración anti-ban
    #[Rule('required|integer|min:3|max:30')]
    public int $delayMin = 5;

    #[Rule('required|integer|min:5|max:60')]
    public int $delayMax = 15;

    #[Rule('required|integer|min:10|max:100')]
    public int $batchSize = 50;

    #[Rule('required|integer|min:60|max:900')]
    public int $batchPause = 300;

    // UI state
    public int $currentStep = 1;
    public bool $isProcessing = false;
    public array $validationErrors = [];

    public function mount(): void
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');

        if (!$isAdmin && !$user->hasPermission('campanas.crear')) {
            session()->flash('error', 'No tienes permiso para crear campañas');
            $this->redirect(route('campaigns.index'));
        }

        // Seleccionar primer canal disponible
        $firstChannel = $this->channels->first();
        if ($firstChannel) {
            $this->channelId = $firstChannel->id;
        }
    }

    #[Computed]
    public function channels(): Collection
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return Channel::where('is_active', true)
                ->where('status', 'connected')
                ->orderBy('name')
                ->get();
        }

        return $user->channels()
            ->where('is_active', true)
            ->where('status', 'connected')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function templates(): Collection
    {
        return CampaignTemplate::where('is_active', true)
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    public function selectTemplate(int $templateId): void
    {
        $template = CampaignTemplate::find($templateId);
        if ($template) {
            $this->templateId = $templateId;
            $this->messageContent = $template->content;
        }
    }

    public function clearTemplate(): void
    {
        $this->templateId = null;
        $this->messageContent = '';
    }

    public function updatedCsvFile(): void
    {
        if ($this->csvFile) {
            $this->parseCsvFile();
        }
    }

    public function parseCsvFile(): void
    {
        if (!$this->csvFile) return;

        $bulkMessageService = app(BulkMessageService::class);
        
        try {
            $this->recipients = $bulkMessageService->parseCsvFile(
                $this->csvFile->getRealPath()
            );
            
            if (empty($this->recipients)) {
                $this->dispatch('toast', type: 'error', message: 'No se encontraron destinatarios válidos en el archivo');
            } else {
                $this->dispatch('toast', type: 'success', message: count($this->recipients) . ' destinatarios cargados');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error al procesar el archivo: ' . $e->getMessage());
            $this->recipients = [];
        }
    }

    public function parseManualNumbers(): void
    {
        if (empty(trim($this->manualNumbers))) {
            $this->dispatch('toast', type: 'error', message: 'Ingresa al menos un número');
            return;
        }

        $lines = preg_split('/[\r\n,;]+/', $this->manualNumbers);
        $newRecipients = [];

        foreach ($lines as $line) {
            $phone = preg_replace('/[^0-9]/', '', trim($line));
            if (strlen($phone) >= 10) {
                $newRecipients[] = [
                    'phone_number' => $phone,
                    'name' => null,
                    'val1' => null,
                    'val2' => null,
                ];
            }
        }

        if (empty($newRecipients)) {
            $this->dispatch('toast', type: 'error', message: 'No se encontraron números válidos');
            return;
        }

        // Agregar a los existentes (evitar duplicados)
        $existingPhones = array_column($this->recipients, 'phone_number');
        foreach ($newRecipients as $recipient) {
            if (!in_array($recipient['phone_number'], $existingPhones)) {
                $this->recipients[] = $recipient;
                $existingPhones[] = $recipient['phone_number'];
            }
        }

        $this->manualNumbers = '';
        $this->dispatch('toast', type: 'success', message: count($newRecipients) . ' números agregados');
    }

    public function removeRecipient(int $index): void
    {
        unset($this->recipients[$index]);
        $this->recipients = array_values($this->recipients);
    }

    public function clearRecipients(): void
    {
        $this->recipients = [];
        $this->csvFile = null;
    }

    public function nextStep(): void
    {
        $this->validationErrors = [];

        // Validar paso actual
        if ($this->currentStep === 1) {
            $this->validate([
                'name' => 'required|min:3|max:100',
                'channelId' => 'required|exists:channels,id',
            ], [
                'name.required' => 'El nombre es requerido',
                'name.min' => 'El nombre debe tener al menos 3 caracteres',
                'channelId.required' => 'Selecciona un canal',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'messageContent' => 'required|min:1|max:4096',
            ], [
                'messageContent.required' => 'El mensaje es requerido',
            ]);
        } elseif ($this->currentStep === 3) {
            if (empty($this->recipients)) {
                $this->dispatch('toast', type: 'error', message: 'Agrega al menos un destinatario');
                return;
            }
        }

        if ($this->currentStep < 4) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 4 && $step <= $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    public function createCampaign(bool $startImmediately = false): void
    {
        $this->validate([
            'name' => 'required|min:3|max:100',
            'channelId' => 'required|exists:channels,id',
            'messageContent' => 'required|min:1|max:4096',
            'delayMin' => 'required|integer|min:3|max:30',
            'delayMax' => 'required|integer|min:5|max:60',
            'batchSize' => 'required|integer|min:10|max:100',
            'batchPause' => 'required|integer|min:60|max:900',
        ]);

        if (empty($this->recipients)) {
            $this->dispatch('toast', type: 'error', message: 'Agrega al menos un destinatario');
            return;
        }

        if ($this->delayMin > $this->delayMax) {
            $this->dispatch('toast', type: 'error', message: 'El delay mínimo no puede ser mayor al máximo');
            return;
        }

        $this->isProcessing = true;

        try {
            // Crear campaña
            $campaign = Campaign::create([
                'name' => $this->name,
                'channel_id' => $this->channelId,
                'user_id' => auth()->id(),
                'template_id' => $this->templateId,
                'message_content' => $this->messageContent,
                'status' => $startImmediately ? 'pending' : 'draft',
                'total_recipients' => count($this->recipients),
                'pending_count' => count($this->recipients),
                'delay_min' => $this->delayMin,
                'delay_max' => $this->delayMax,
                'batch_size' => $this->batchSize,
                'batch_pause' => $this->batchPause,
            ]);

            // Crear destinatarios
            foreach ($this->recipients as $recipient) {
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'phone_number' => $recipient['phone_number'],
                    'name' => $recipient['name'] ?? null,
                    'val1' => $recipient['val1'] ?? null,
                    'val2' => $recipient['val2'] ?? null,
                    'status' => 'pending',
                ]);
            }

            // Si se debe iniciar inmediatamente, despachar job
            if ($startImmediately) {
                ProcessCampaignJob::dispatch($campaign);
                $this->dispatch('toast', type: 'success', message: 'Campaña creada e iniciada');
            } else {
                $this->dispatch('toast', type: 'success', message: 'Campaña guardada como borrador');
            }

            $this->redirect(route('campaigns.index'));

        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error al crear la campaña: ' . $e->getMessage());
        }

        $this->isProcessing = false;
    }

    public function render()
    {
        return view('livewire.campaigns.create');
    }
}
