<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;

class SyncModulesAndPermissions extends Command
{
    protected $signature = 'permissions:sync';
    protected $description = 'Sincroniza módulos y permisos sin eliminar datos existentes';

    public function handle(): int
    {
        $this->info('Sincronizando módulos y permisos...');

        // Módulos estándar con permisos CRUD
        $standardModules = [
            ['name' => 'dashboard', 'display_name' => 'Dashboard', 'icon' => 'home', 'order' => 1],
            ['name' => 'canales', 'display_name' => 'Canales', 'icon' => 'chat', 'order' => 2],
            ['name' => 'usuarios', 'display_name' => 'Usuarios', 'icon' => 'users', 'order' => 3],
            ['name' => 'roles', 'display_name' => 'Roles', 'icon' => 'shield', 'order' => 4],
            ['name' => 'clientes', 'display_name' => 'Clientes', 'icon' => 'users', 'order' => 5],
            ['name' => 'cobranzas', 'display_name' => 'Cobranzas', 'icon' => 'clipboard', 'order' => 6],
            ['name' => 'reportes', 'display_name' => 'Reportes', 'icon' => 'chart', 'order' => 7],
        ];

        // Módulos con permisos personalizados
        $customModules = [
            'chats' => [
                'module' => ['name' => 'chats', 'display_name' => 'Chats', 'icon' => 'message-circle', 'order' => 8],
                'permissions' => [
                    ['action' => 'ver', 'display_name' => 'Ver Chats'],
                    ['action' => 'enviar', 'display_name' => 'Enviar Mensajes'],
                    ['action' => 'etiquetas', 'display_name' => 'Gestionar Etiquetas'],
                ],
            ],
        ];

        $actions = Module::getActions();
        $newPermissions = collect();

        // Procesar módulos estándar
        foreach ($standardModules as $moduleData) {
            $module = Module::updateOrCreate(
                ['name' => $moduleData['name']],
                $moduleData
            );

            foreach ($actions as $actionKey => $actionName) {
                $permission = Permission::updateOrCreate(
                    ['name' => $module->name . '.' . $actionKey],
                    [
                        'module_id' => $module->id,
                        'display_name' => $actionName . ' ' . $module->display_name,
                        'action' => $actionKey,
                    ]
                );

                if ($permission->wasRecentlyCreated) {
                    $newPermissions->push($permission);
                    $this->line("  + Permiso creado: {$permission->name}");
                }
            }

            $this->info("✓ Módulo sincronizado: {$module->display_name}");
        }

        // Procesar módulos con permisos personalizados
        foreach ($customModules as $key => $config) {
            $module = Module::updateOrCreate(
                ['name' => $config['module']['name']],
                $config['module']
            );

            foreach ($config['permissions'] as $permData) {
                $permission = Permission::updateOrCreate(
                    ['name' => $module->name . '.' . $permData['action']],
                    [
                        'module_id' => $module->id,
                        'display_name' => $permData['display_name'],
                        'action' => $permData['action'],
                    ]
                );

                if ($permission->wasRecentlyCreated) {
                    $newPermissions->push($permission);
                    $this->line("  + Permiso creado: {$permission->name}");
                }
            }

            $this->info("✓ Módulo sincronizado: {$module->display_name}");
        }

        // Asignar nuevos permisos al rol admin
        if ($newPermissions->isNotEmpty()) {
            $adminRole = Role::where('name', 'admin')->first();
            if ($adminRole) {
                $adminRole->permissions()->syncWithoutDetaching($newPermissions->pluck('id'));
                $this->info("✓ {$newPermissions->count()} permisos nuevos asignados al rol admin");
            }
        }

        $this->newLine();
        $this->info('¡Sincronización completada!');

        return Command::SUCCESS;
    }
}
