<?php

namespace App\Livewire\Settings\Roles;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Rule;

#[Layout('layouts.app')]
#[Title('Crear Rol')]
class Create extends Component
{
    #[Rule('required|string|max:255|unique:roles,name')]
    public string $name = '';

    #[Rule('required|string|max:255')]
    public string $display_name = '';

    #[Rule('nullable|string|max:500')]
    public string $description = '';

    #[Rule('required|string')]
    public string $color = '#6b7280';

    public array $selectedPermissions = [];

    public function updatedSelectedPermissions(): void
    {
        if (is_array($this->selectedPermissions)) {
            $this->selectedPermissions = array_values(array_map('intval', $this->selectedPermissions));
        }
    }

    public function save(): void
    {
        $this->validate();

        $role = Role::create([
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'color' => $this->color,
        ]);

        $role->permissions()->sync($this->selectedPermissions);

        session()->flash('toast', ['type' => 'success', 'message' => 'Rol creado correctamente']);
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

        return view('livewire.settings.roles.create', [
            'modules' => $modules,
        ]);
    }
}
