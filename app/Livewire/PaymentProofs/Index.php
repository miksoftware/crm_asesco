<?php

namespace App\Livewire\PaymentProofs;

use App\Models\PaymentProof;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Soportes de Pago')]
class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $statusFilter = '';

    #[Url(history: true)]
    public string $dateFilter = '';

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public int $perPage = 25;

    public bool $canDownload = false;

    // Download modal state
    public bool $showDownloadModal = false;
    public ?int $downloadProofId = null;
    public string $downloadFileName = '';
    public string $downloadExtension = '';

    public function mount(): void
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $this->canDownload = $isAdmin || $user->hasPermission('soportes_pago.descargar');
    }

    #[Computed]
    public function proofs()
    {
        $query = PaymentProof::with(['contact', 'channel', 'user', 'downloader']);

        // Si no es admin, mostrar solo los propios o los de su canal
        $user = auth()->user();
        if (!$user->hasRole('admin')) {
            $userChannels = $user->channels()->pluck('channels.id');
            $query->where(function ($q) use ($user, $userChannels) {
                $q->where('user_id', $user->id)
                  ->orWhereIn('channel_id', $userChannels);
            });
        }

        if (!empty($this->search)) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('phone_number', 'like', $term)
                  ->orWhere('client_name', 'like', $term)
                  ->orWhereHas('contact', function ($q) use ($term) {
                      $q->where('name', 'like', $term)
                        ->orWhere('push_name', 'like', $term);
                  });
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->dateFilter)) {
            $query->whereDate('created_at', $this->dateFilter);
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

    public function openDownloadModal(int $id): void
    {
        if (!$this->canDownload) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para descargar soportes');
            return;
        }

        $proof = PaymentProof::find($id);
        if (!$proof || !$proof->file_path) {
            $this->dispatch('toast', type: 'error', message: 'Soporte no encontrado');
            return;
        }

        if (!Storage::disk('public')->exists($proof->file_path)) {
            $this->dispatch('toast', type: 'error', message: 'El archivo ya no existe');
            return;
        }

        $this->downloadProofId = $id;

        // Sugerir nombre por defecto: teléfono del cliente
        $this->downloadFileName = $proof->phone_number;

        // Extraer extensión del archivo original
        $this->downloadExtension = pathinfo($proof->file_name ?? '', PATHINFO_EXTENSION) ?: 'jpg';

        $this->showDownloadModal = true;
    }

    public function closeDownloadModal(): void
    {
        $this->showDownloadModal = false;
        $this->downloadProofId = null;
        $this->downloadFileName = '';
        $this->downloadExtension = '';
        $this->resetValidation();
    }

    public function confirmDownload()
    {
        if (!$this->canDownload) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para descargar soportes');
            return;
        }

        $this->validate([
            'downloadFileName' => 'required|string|min:1|max:200',
        ], [
            'downloadFileName.required' => 'Ingresa un nombre para el archivo',
        ]);

        $proof = PaymentProof::find($this->downloadProofId);

        if (!$proof || !$proof->file_path) {
            $this->dispatch('toast', type: 'error', message: 'Soporte no encontrado');
            $this->closeDownloadModal();
            return;
        }

        if (!Storage::disk('public')->exists($proof->file_path)) {
            $this->dispatch('toast', type: 'error', message: 'El archivo ya no existe');
            $this->closeDownloadModal();
            return;
        }

        // Limpiar nombre del archivo (quitar caracteres no válidos)
        $cleanName = preg_replace('/[^A-Za-z0-9._\-]/', '_', trim($this->downloadFileName));
        $cleanName = $cleanName ?: 'soporte_' . $proof->id;

        // Quitar extensión si el usuario la incluyó, para asegurar que coincida con el archivo
        $cleanName = preg_replace('/\.' . preg_quote($this->downloadExtension, '/') . '$/i', '', $cleanName);

        $finalFileName = $cleanName . '.' . $this->downloadExtension;

        // Marcar como descargado
        $proof->update([
            'status' => 'downloaded',
            'downloaded_at' => now(),
            'downloaded_by' => auth()->id(),
        ]);

        // URL pública del archivo para que el JS lo descargue con "Guardar como"
        $fileUrl = Storage::disk('public')->url($proof->file_path);

        $this->showDownloadModal = false;
        $this->downloadProofId = null;

        // Disparar evento JS que abre el "Guardar como" del navegador
        $this->dispatch('trigger-save-as',
            url: $fileUrl,
            fileName: $finalFileName,
            mimeType: $proof->mime_type ?? 'application/octet-stream'
        );

        $this->downloadFileName = '';
        $this->downloadExtension = '';
    }

    public function deleteProof(int $id): void
    {
        $proof = PaymentProof::find($id);

        if (!$proof) {
            $this->dispatch('toast', type: 'error', message: 'Soporte no encontrado');
            return;
        }

        // Solo admin o el creador puede eliminar
        $user = auth()->user();
        if (!$user->hasRole('admin') && $proof->user_id !== $user->id) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para eliminar este soporte');
            return;
        }

        if ($proof->file_path && Storage::disk('public')->exists($proof->file_path)) {
            Storage::disk('public')->delete($proof->file_path);
        }

        $proof->delete();
        $this->dispatch('toast', type: 'success', message: 'Soporte eliminado');
    }

    public function render()
    {
        return view('livewire.payment-proofs.index');
    }
}
