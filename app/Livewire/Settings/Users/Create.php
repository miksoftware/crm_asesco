<?php

namespace App\Livewire\Settings\Users;

use App\Models\Role;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Hash;

#[Layout('layouts.app')]
#[Title('Crear Usuario')]
class Create extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email|unique:users,email')]
    public string $email = '';

    #[Rule('required|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public array $selectedRoles = [];

    public function save(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        $user->roles()->sync($this->selectedRoles);

        session()->flash('toast', ['type' => 'success', 'message' => 'Usuario creado correctamente']);
        $this->redirectRoute('settings.users.index');
    }

    public function render()
    {
        return view('livewire.settings.users.create', [
            'roles' => Role::where('is_active', true)->get(),
        ]);
    }
}
