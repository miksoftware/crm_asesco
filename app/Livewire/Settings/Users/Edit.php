<?php

namespace App\Livewire\Settings\Users;

use App\Models\Role;
use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule as ValidationRule;

#[Layout('layouts.app')]
#[Title('Editar Usuario')]
class Edit extends Component
{
    use WithFileUploads;

    public User $user;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public array $selectedRoles = [];
    public $photo = null;
    public bool $isActive = true;

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->isActive = $user->is_active ?? true;
        $this->selectedRoles = $user->roles->pluck('id')->toArray();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', ValidationRule::unique('users', 'email')->ignore($this->user->id)],
            'password' => 'nullable|min:8|confirmed',
            'photo' => 'nullable|image|max:2048',
        ];
    }

    public function removePhoto(): void
    {
        if ($this->user->profile_photo_path) {
            Storage::disk('public')->delete($this->user->profile_photo_path);
            $this->user->update(['profile_photo_path' => null]);
            $this->dispatch('toast', type: 'success', message: 'Foto eliminada correctamente');
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->isActive,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->photo) {
            // Delete old photo if exists
            if ($this->user->profile_photo_path) {
                Storage::disk('public')->delete($this->user->profile_photo_path);
            }
            $data['profile_photo_path'] = $this->photo->store('profile-photos', 'public');
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

