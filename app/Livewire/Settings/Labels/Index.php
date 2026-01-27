<?php

namespace App\Livewire\Settings\Labels;

use App\Models\Label;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Layout('layouts.app')]
#[Title('Etiquetas')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public int $perPage = 10;
    public string $sortField = 'order';
    public string $sortDirection = 'asc';

    public bool $canCreate = false;
    public bool $canEdit = false;
    public bool $canDelete = false;

    // Modal para crear/editar
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $color = '#6b7280';
    public int $order = 0;

    public function mount(): void
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');

        $this->canCreate = $isAdmin || $user->hasPermission('etiquetas.crear');
        $this->canEdit = $isAdmin || $user->hasPermission('etiquetas.editar');
        $this->canDelete = $isAdmin || $user->hasPermission('etiquetas.eliminar');
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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getLabelsProperty()
    {
        return Label::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function openCreateModal(): void
    {
        $this->resetModal();
        $this->order = Label::max('order') + 1;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $label = Label::findOrFail($id);
        $this->editingId = $label->id;
        $this->name = $label->name;
        $this->color = $label->color;
        $this->order = $label->order;
        $this->showModal = true;
    }

    public function resetModal(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->color = '#6b7280';
        $this->order = 0;
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetModal();
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|min:2|max:50',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'order' => 'required|integer|min:0',
        ], [
            'name.required' => 'El nombre es requerido',
            'name.min' => 'El nombre debe tener al menos 2 caracteres',
            'color.regex' => 'El color debe ser un código hexadecimal válido',
        ]);

        $data = [
            'name' => $this->name,
            'color' => $this->color,
            'order' => $this->order,
        ];

        if ($this->editingId) {
            $label = Label::findOrFail($this->editingId);
            $label->update($data);
            $message = 'Etiqueta actualizada';
        } else {
            Label::create($data);
            $message = 'Etiqueta creada';
        }

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function delete(int $id): void
    {
        $label = Label::findOrFail($id);

        if ($label->is_system) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar una etiqueta del sistema');
            return;
        }

        // Desasociar de todos los contactos
        $label->contacts()->detach();
        $label->delete();

        $this->dispatch('toast', type: 'success', message: 'Etiqueta eliminada');
    }

    public function render()
    {
        return view('livewire.settings.labels.index');
    }
}
