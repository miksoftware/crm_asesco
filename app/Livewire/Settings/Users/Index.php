<?php

namespace App\Livewire\Settings\Users;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

#[Layout('layouts.app')]
#[Title('Usuarios')]
class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortBy = 'created_at';

    #[Url(history: true)]
    public string $sortDir = 'desc';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->dispatch('confirm-delete', id: $id, message: 'El usuario será eliminado permanentemente');
    }

    #[On('deleteConfirmed')]
    public function delete(int $id): void
    {
        $user = User::find($id);
        
        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'Usuario no encontrado');
            return;
        }

        if ($user->id === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'No puedes eliminar tu propio usuario');
            return;
        }

        $user->delete();
        $this->dispatch('toast', type: 'success', message: 'Usuario eliminado correctamente');
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        return view('livewire.settings.users.index', [
            'users' => $users,
        ]);
    }
}
