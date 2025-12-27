<?php

namespace App\Livewire\Settings\Users;

use App\Models\Role;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule as ValidationRule;

#[Layout('layouts.app')]
#[Title('Editar Usuario')]
class Edit extends Component
{
    public User $user;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public array $selectedRoles = [];

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->selectedRoles = $user->roles->pluck('id')->toArray();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', ValidationRule::unique('users', 'email')->ignore($this->user->id)],
            'password' => 'nullable|min:8|confirmed',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $this->user->update($data);
        $this->user->roles()->sync($this->selectedRoles);

        session()->flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado correctamente']);
        $this->redirectRoute('settings.users.index');
    }

    public function render()
    {
        return view('livewire.settings.users.edit', [
            'roles' => Role::where('is_active', true)->get(),
        ]);
    }
}
