<?php

namespace App\Livewire\Campaigns;

use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Channel;
use App\Services\BulkMessageService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

#[Layout('layouts.app')]
#[Title('Campañas')]
class Index extends Component
{
    use WithPagination, WithFileUploads;

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

    // Excel modal state
    public bool $showExcelModal = false;
    public $excelFile = null;
    public ?int $excelChannelId = null;
    public string $excelCampaignName = '';
    public string $excelMessage = '';
    public array $excelPreview = [];
    public int $excelTotalRows = 0;
    public bool $excelProcessing = false;

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

    // ─── Excel Campaign Modal ───────────────────────────────────

    #[Computed]
    public function availableChannels(): Collection
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

    public function openExcelModal(): void
    {
        $this->resetExcelModal();
        $this->showExcelModal = true;

        $firstChannel = $this->availableChannels->first();
        if ($firstChannel) {
            $this->excelChannelId = $firstChannel->id;
        }
    }

    public function closeExcelModal(): void
    {
        $this->showExcelModal = false;
        $this->resetExcelModal();
    }

    private function resetExcelModal(): void
    {
        $this->excelFile = null;
        $this->excelChannelId = null;
        $this->excelCampaignName = '';
        $this->excelMessage = '';
        $this->excelPreview = [];
        $this->excelTotalRows = 0;
        $this->excelProcessing = false;
        $this->resetValidation();
    }

    public function downloadExcelTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Destinatarios');

        // Headers
        $headers = ['telefono', 'nombre', 'val1', 'val2'];
        $headerLabels = ['Teléfono *', 'Nombre', 'Variable 1', 'Variable 2'];

        foreach ($headerLabels as $col => $label) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $label);
        }

        // Estilo de headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F97316']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

        // Ejemplo de datos
        $examples = [
            ['3001234567', 'Juan Pérez', '$150.000', '15/02/2026'],
            ['3109876543', 'María López', '$200.000', '20/02/2026'],
            ['3201112233', 'Carlos García', '$75.000', '28/02/2026'],
        ];

        foreach ($examples as $row => $data) {
            foreach ($data as $col => $value) {
                $sheet->setCellValue(chr(65 + $col) . ($row + 2), $value);
            }
        }

        // Estilo de ejemplos (gris claro)
        $sheet->getStyle('A2:D4')->applyFromArray([
            'font' => ['color' => ['rgb' => '9CA3AF'], 'italic' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        // Ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);

        // Nota en fila 6
        $sheet->setCellValue('A6', 'INSTRUCCIONES:');
        $sheet->setCellValue('A7', '- La columna Teléfono es obligatoria (10 dígitos sin código de país, o con código 57)');
        $sheet->setCellValue('A8', '- Elimine las filas de ejemplo antes de agregar sus datos');
        $sheet->setCellValue('A9', '- Use {nombre}, {val1}, {val2} como variables en el mensaje de la campaña');
        $sheet->getStyle('A6')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B21'));
        $sheet->getStyle('A7:A9')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6B7280'));

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'plantilla_campana_excel.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function updatedExcelFile(): void
    {
        if (!$this->excelFile) return;

        $this->validate([
            'excelFile' => 'required|file|max:5120|mimes:xlsx,xls',
        ], [
            'excelFile.max' => 'El archivo no debe superar 5MB',
            'excelFile.mimes' => 'Solo se permiten archivos Excel (.xlsx, .xls)',
        ]);

        try {
            $bulkService = app(BulkMessageService::class);
            $parsed = $bulkService->parseExcelFile($this->excelFile->getRealPath());

            $this->excelTotalRows = count($parsed);
            $this->excelPreview = array_slice($parsed, 0, 5);

            if ($this->excelTotalRows === 0) {
                $this->dispatch('toast', type: 'error', message: 'No se encontraron destinatarios válidos en el archivo');
            } else {
                $this->dispatch('toast', type: 'success', message: $this->excelTotalRows . ' destinatarios encontrados');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error al leer el archivo: ' . $e->getMessage());
            $this->excelFile = null;
            $this->excelPreview = [];
            $this->excelTotalRows = 0;
        }
    }

    public function processExcelCampaign(): void
    {
        $this->validate([
            'excelCampaignName' => 'required|min:3|max:100',
            'excelChannelId' => 'required|exists:channels,id',
            'excelMessage' => 'required|min:1|max:4096',
            'excelFile' => 'required',
        ], [
            'excelCampaignName.required' => 'El nombre de la campaña es requerido',
            'excelCampaignName.min' => 'El nombre debe tener al menos 3 caracteres',
            'excelChannelId.required' => 'Selecciona un canal',
            'excelMessage.required' => 'El mensaje es requerido',
            'excelFile.required' => 'Sube un archivo Excel',
        ]);

        if ($this->excelTotalRows === 0) {
            $this->dispatch('toast', type: 'error', message: 'No hay destinatarios para procesar');
            return;
        }

        $this->excelProcessing = true;

        try {
            $bulkService = app(BulkMessageService::class);
            $recipients = $bulkService->parseExcelFile($this->excelFile->getRealPath());

            // Crear campaña con valores anti-ban por defecto
            $campaign = Campaign::create([
                'name' => $this->excelCampaignName,
                'channel_id' => $this->excelChannelId,
                'user_id' => auth()->id(),
                'message_content' => $this->excelMessage,
                'status' => 'pending',
                'total_recipients' => count($recipients),
                'pending_count' => count($recipients),
                'delay_min' => 5,
                'delay_max' => 15,
                'batch_size' => 50,
                'batch_pause' => 300,
            ]);

            // Crear destinatarios en lotes
            $chunks = array_chunk($recipients, 500);
            foreach ($chunks as $chunk) {
                $rows = [];
                foreach ($chunk as $r) {
                    $rows[] = [
                        'campaign_id' => $campaign->id,
                        'phone_number' => $r['phone_number'],
                        'name' => $r['name'] ?? null,
                        'val1' => $r['val1'] ?? null,
                        'val2' => $r['val2'] ?? null,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                CampaignRecipient::insert($rows);
            }

            // Despachar job
            ProcessCampaignJob::dispatch($campaign);

            $this->closeExcelModal();
            unset($this->campaigns);

            $this->dispatch('toast', type: 'success', message: 'Campaña creada e iniciada con ' . count($recipients) . ' destinatarios');

        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error al crear la campaña: ' . $e->getMessage());
        }

        $this->excelProcessing = false;
    }

    public function render()
    {
        return view('livewire.campaigns.index');
    }
}
