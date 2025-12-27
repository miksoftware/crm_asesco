<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Crear módulos
        $modules = [
            ['name' => 'dashboard', 'display_name' => 'Dashboard', 'icon' => 'home', 'order' => 1],
            ['name' => 'canales', 'display_name' => 'Canales', 'icon' => 'chat', 'order' => 2],
            ['name' => 'usuarios', 'display_name' => 'Usuarios', 'icon' => 'users', 'order' => 3],
            ['name' => 'roles', 'display_name' => 'Roles', 'icon' => 'shield', 'order' => 4],
            ['name' => 'clientes', 'display_name' => 'Clientes', 'icon' => 'users', 'order' => 5],
            ['name' => 'cobranzas', 'display_name' => 'Cobranzas', 'icon' => 'clipboard', 'order' => 6],
            ['name' => 'reportes', 'display_name' => 'Reportes', 'icon' => 'chart', 'order' => 7],
        ];

        $actions = Module::getActions();

        foreach ($modules as $moduleData) {
            $module = Module::create($moduleData);

            // Crear permisos para cada módulo
            foreach ($actions as $actionKey => $actionName) {
                Permission::create([
                    'module_id' => $module->id,
                    'name' => $module->name . '.' . $actionKey,
                    'display_name' => $actionName . ' ' . $module->display_name,
                    'action' => $actionKey,
                ]);
            }
        }

        // Crear rol de Administrador con todos los permisos
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
            'description' => 'Acceso total al sistema',
            'color' => '#ef4444',
        ]);

        $adminRole->permissions()->attach(Permission::all());

        // Crear rol de Usuario básico
        $userRole = Role::create([
            'name' => 'usuario',
            'display_name' => 'Usuario',
            'description' => 'Acceso básico al sistema',
            'color' => '#3b82f6',
        ]);

        // Asignar permisos básicos al usuario
        $basicPermissions = Permission::whereIn('name', [
            'dashboard.ver',
            'clientes.ver',
            'cobranzas.ver',
        ])->get();

        $userRole->permissions()->attach($basicPermissions);

        // Asignar rol admin al primer usuario
        $admin = User::first();
        if ($admin) {
            $admin->assignRole($adminRole);
        }
    }
}
