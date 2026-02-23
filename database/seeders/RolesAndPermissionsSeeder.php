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
        // Crear módulos con permisos estándar (CRUD)
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
            $module = Module::firstOrCreate(
                ['name' => $moduleData['name']],
                $moduleData
            );

            // Crear permisos para cada módulo
            foreach ($actions as $actionKey => $actionName) {
                Permission::firstOrCreate(
                    ['name' => $module->name . '.' . $actionKey],
                    [
                        'module_id' => $module->id,
                        'display_name' => $actionName . ' ' . $module->display_name,
                        'action' => $actionKey,
                    ]
                );
            }
        }

        // Crear módulo de Chats con permisos personalizados
        $chatsModule = Module::firstOrCreate(
            ['name' => 'chats'],
            [
                'display_name' => 'Chats',
                'icon' => 'message-circle',
                'order' => 8,
            ]
        );

        // Permisos específicos del módulo de chat
        $chatPermissions = [
            ['action' => 'ver', 'display_name' => 'Ver Chats'],
            ['action' => 'enviar', 'display_name' => 'Enviar Mensajes'],
            ['action' => 'etiquetas', 'display_name' => 'Gestionar Etiquetas'],
        ];

        foreach ($chatPermissions as $permData) {
            Permission::firstOrCreate(
                ['name' => 'chats.' . $permData['action']],
                [
                    'module_id' => $chatsModule->id,
                    'display_name' => $permData['display_name'],
                    'action' => $permData['action'],
                ]
            );
        }

        // Crear rol de Administrador con todos los permisos
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrador',
                'description' => 'Acceso total al sistema',
                'color' => '#ef4444',
            ]
        );

        $adminRole->permissions()->syncWithoutDetaching(Permission::pluck('id'));

        // Crear rol de Usuario básico
        $userRole = Role::firstOrCreate(
            ['name' => 'usuario'],
            [
                'display_name' => 'Usuario',
                'description' => 'Acceso básico al sistema',
                'color' => '#3b82f6',
            ]
        );

        // Asignar permisos básicos al usuario
        $basicPermissions = Permission::whereIn('name', [
            'dashboard.ver',
            'clientes.ver',
            'cobranzas.ver',
        ])->get();

        $userRole->permissions()->syncWithoutDetaching($basicPermissions->pluck('id'));

        // Asignar rol admin al primer usuario
        $admin = User::first();
        if ($admin) {
            $admin->assignRole($adminRole);
        }
    }
}
