<?php

namespace App\Livewire\Settings\Roles;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Validation\Rule as ValidationRule;

#[Layout('layouts.app')]
#[Title('Editar Rol')]
class Edit extends Component
{
    public Role $role;

    public string $name = '';
    public string $display_name = '';
    public string $description = '';
    public string $color = '#6b7280';
    public array $selectedPermissions = [];

    public function mount(Role $role): void
    {
        $this->role = $role;
        $this->name = $role->name;
        $this->display_name = $role->display_name;
        $this->description = $role->description ?? '';
        $this->color = $role->color;
        $this->selectedPermissions = array_values(array_map('intval', $role->permissions->pluck('id')->toArray()));
    }

    public function updatedSelectedPermissions(): void
    {
        if (is_array($this->selectedPermissions)) {
            $this->selectedPermissions = array_values(array_map('intval', $this->selectedPermissions));
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', ValidationRule::unique('roles', 'name')->ignore($this->role->id)],
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'color' => 'required|string',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $this->role->update([
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'color' => $this->color,
        ]);

        $this->role->permissions()->sync($this->selectedPermissions);

        session()->flash('toast', ['type' => 'success', 'message' => 'Rol actualizado correctamente']);
        $this->redirectRoute('settings.roles.index');
    }

    public function toggleModule(int $moduleId): void
    {
        $permissions = Permission::where('module_id', $moduleId)->pluck('id')->toArray();
        $allSelected = count(array_intersect($permissions, $this->selectedPermissions)) === count($permissions);

        if ($allSelected) {
            $this->selectedPermissions = array_values(array_map('intval', array_diff($this->selectedPermissions, $permissions)));
        } else {
            $this->selectedPermissions = array_values(array_map('intval', array_unique(array_merge($this->selectedPermissions, $permissions))));
        }
    }

    public function render()
    {
        $modules = Module::with('permissions')->orderBy('order')->get();

        return view('livewire.settings.roles.edit', [
            'modules' => $modules,
        ]);
    }
}
