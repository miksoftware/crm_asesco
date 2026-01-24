<?php

namespace App\Livewire\Settings\Users;

use App\Models\Role;
use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Hash;

#[Layout('layouts.app')]
#[Title('Crear Usuario')]
class Create extends Component
{
    use WithFileUploads;

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email|unique:users,email')]
    public string $email = '';

    #[Rule('required|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    #[Rule('nullable|image|max:2048')]
    public $photo = null;

    public array $selectedRoles = [];

    public bool $isActive = true;

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'is_active' => $this->isActive,
        ];

        if ($this->photo) {
            $data['profile_photo_path'] = $this->photo->store('profile-photos', 'public');
        }

        $user = User::create($data);

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
