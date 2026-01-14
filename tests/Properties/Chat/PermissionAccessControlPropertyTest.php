<?php

namespace Tests\Properties\Chat;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Permission-Based Access Control
 * 
 * Feature: chat-module, Property 22: Permission-Based Access Control
 * Validates: Requirements 10.1, 10.2, 10.3, 10.4
 * 
 * For any user and any protected operation (view chat, send message, manage labels),
 * the operation SHALL succeed only if the user has the corresponding permission
 * (chats.ver, chats.enviar, chats.etiquetas).
 */
class PermissionAccessControlPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_PERMISSIONS = [
        'chats.ver' => 'Ver Chats',
        'chats.enviar' => 'Enviar Mensajes',
        'chats.etiquetas' => 'Gestionar Etiquetas',
    ];

    /**
     * Property 22: Permission-Based Access Control
     * 
     * For any user with a random subset of chat permissions, the hasPermission
     * method should return true only for permissions the user actually has.
     * 
     * @test
     */
    public function permission_based_access_control_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runPermissionPropertyIteration();
        }
    }

    private function runPermissionPropertyIteration(): void
    {
        // Create the chats module
        $chatsModule = Module::create([
            'name' => 'chats',
            'display_name' => 'Chats',
            'icon' => 'message-circle',
            'order' => 1,
        ]);

        // Create all chat permissions
        $allPermissions = [];
        foreach (self::CHAT_PERMISSIONS as $name => $displayName) {
            $action = explode('.', $name)[1];
            $allPermissions[$name] = Permission::create([
                'module_id' => $chatsModule->id,
                'name' => $name,
                'display_name' => $displayName,
                'action' => $action,
            ]);
        }

        // Create a role with random subset of permissions
        $role = Role::create([
            'name' => 'test_role_' . uniqid(),
            'display_name' => 'Test Role',
            'description' => 'Test role for property testing',
            'color' => '#' . dechex(rand(0x000000, 0xFFFFFF)),
        ]);

        // Randomly select which permissions to grant (0 to all)
        $permissionNames = array_keys(self::CHAT_PERMISSIONS);
        $grantedCount = rand(0, count($permissionNames));
        shuffle($permissionNames);
        $grantedPermissions = array_slice($permissionNames, 0, $grantedCount);

        // Attach granted permissions to role
        foreach ($grantedPermissions as $permName) {
            $role->permissions()->attach($allPermissions[$permName]);
        }

        // Create a user and assign the role
        $user = User::factory()->create();
        $user->assignRole($role);

        // PROPERTY: For each permission, hasPermission should return true
        // if and only if the permission was granted
        foreach (self::CHAT_PERMISSIONS as $permName => $displayName) {
            $hasPermission = $user->hasPermission($permName);
            $shouldHavePermission = in_array($permName, $grantedPermissions);

            if ($shouldHavePermission) {
                $this->assertTrue(
                    $hasPermission,
                    "User should have permission '{$permName}' but hasPermission returned false"
                );
            } else {
                $this->assertFalse(
                    $hasPermission,
                    "User should NOT have permission '{$permName}' but hasPermission returned true"
                );
            }
        }

        // Clean up for next iteration
        $user->roles()->detach();
        $role->permissions()->detach();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        Module::query()->delete();
    }

    /**
     * Property 22 Extension: Admin role has all permissions
     * 
     * For any user with admin role, all chat permissions should be granted.
     * 
     * @test
     */
    public function admin_role_has_all_chat_permissions_property(): void
    {
        // Run 100 iterations
        for ($i = 0; $i < 100; $i++) {
            $this->runAdminPermissionPropertyIteration();
        }
    }

    private function runAdminPermissionPropertyIteration(): void
    {
        // Create the chats module
        $chatsModule = Module::create([
            'name' => 'chats',
            'display_name' => 'Chats',
            'icon' => 'message-circle',
            'order' => 1,
        ]);

        // Create all chat permissions
        $allPermissions = [];
        foreach (self::CHAT_PERMISSIONS as $name => $displayName) {
            $action = explode('.', $name)[1];
            $allPermissions[$name] = Permission::create([
                'module_id' => $chatsModule->id,
                'name' => $name,
                'display_name' => $displayName,
                'action' => $action,
            ]);
        }

        // Create admin role with all permissions
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
            'description' => 'Acceso total al sistema',
            'color' => '#ef4444',
        ]);

        // Attach all permissions to admin role
        $adminRole->permissions()->attach(Permission::all());

        // Create a user and assign admin role
        $user = User::factory()->create();
        $user->assignRole($adminRole);

        // PROPERTY: Admin user should have ALL chat permissions
        foreach (self::CHAT_PERMISSIONS as $permName => $displayName) {
            $this->assertTrue(
                $user->hasPermission($permName),
                "Admin user should have permission '{$permName}' but hasPermission returned false"
            );
        }

        // Clean up for next iteration
        $user->roles()->detach();
        $adminRole->permissions()->detach();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        Module::query()->delete();
    }
}
