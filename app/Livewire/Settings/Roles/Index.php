<?php

namespace App\Livewire\Settings\Roles;

use App\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

#[Layout('layouts.app')]
#[Title('Roles')]
class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public int $perPage = 10;

    public bool $canCreate = false;
    public bool $canEdit = false;
    public bool $canDelete = false;

    public function mount(): void
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        
        $this->canCreate = $isAdmin || $user->hasPermission('roles.crear');
        $this->canEdit = $isAdmin || $user->hasPermission('roles.editar');
        $this->canDelete = $isAdmin || $user->hasPermission('roles.eliminar');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        if (!$this->canDelete) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para eliminar roles');
            return;
        }
        
        $this->dispatch('confirm-delete', id: $id, message: 'El rol y sus asignaciones serán eliminados');
    }

    #[On('deleteConfirmed')]
    public function delete(int $id): void
    {
        if (!$this->canDelete) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para eliminar roles');
            return;
        }

        $role = Role::find($id);

        if (!$role) {
            $this->dispatch('toast', type: 'error', message: 'Rol no encontrado');
            return;
        }

        if ($role->name === 'admin') {
            $this->dispatch('toast', type: 'error', message: 'No puedes eliminar el rol de administrador');
            return;
        }

        $role->delete();
        $this->dispatch('toast', type: 'success', message: 'Rol eliminado correctamente');
    }

    public function render()
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($this->search, function ($query) {
                $query->where('display_name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.settings.roles.index', [
            'roles' => $roles,
        ]);
    }
}
