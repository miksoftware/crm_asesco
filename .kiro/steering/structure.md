# Project Structure

## Estructura de Directorios

```
app/
├── Console/
│   └── Commands/
│       └── SyncModulesAndPermissions.php  # Comando para sincronizar permisos
├── Http/
│   ├── Controllers/           # Controladores tradicionales (uso mínimo)
│   └── Middleware/
│       └── CheckPermission.php # Middleware de verificación de permisos
├── Livewire/                  # Componentes Livewire (patrón principal)
│   ├── Auth/
│   │   ├── Login.php          # Componente de login
│   │   └── Logout.php         # Componente de logout
│   ├── Channels/
│   │   ├── Index.php          # Lista de canales WhatsApp
│   │   ├── Create.php         # Crear canal
│   │   └── Edit.php           # Editar canal
│   ├── Chat/
│   │   ├── Index.php          # Interfaz principal de chat
│   │   ├── NotificationBadge.php  # Badge de notificaciones
│   │   ├── ContactInfo.php    # Panel de información de contacto
│   │   └── QuickActions.php   # Acciones rápidas del chat
│   ├── Dashboard.php          # Dashboard principal
│   └── Settings/
│       ├── Users/
│       │   ├── Index.php      # Lista de usuarios
│       │   ├── Create.php     # Crear usuario
│       │   └── Edit.php       # Editar usuario
│       └── Roles/
│           ├── Index.php      # Lista de roles
│           ├── Create.php     # Crear rol
│           └── Edit.php       # Editar rol
├── Models/
│   ├── User.php               # Usuario con roles, canales y permisos
│   ├── Role.php               # Rol con permisos
│   ├── Permission.php         # Permiso individual
│   ├── Module.php             # Módulo del sistema
│   ├── Channel.php            # Canal WhatsApp
│   ├── Contact.php            # Contacto de WhatsApp
│   ├── Conversation.php       # Conversación de chat
│   ├── Message.php            # Mensaje de chat
│   ├── Label.php              # Etiqueta para contactos
│   └── ChatNotification.php   # Notificación de chat
├── Providers/                 # Service providers
└── Services/
    ├── EvolutionApiService.php # Cliente HTTP para Evolution API
    └── NotificationService.php # Servicio de notificaciones

bootstrap/
└── app.php                    # Registro de middleware 'permission'

resources/
├── css/
│   └── app.css                # Tailwind con @theme para colores custom
├── js/
│   ├── app.js                 # Entry point (NO importar Alpine aquí)
│   └── bootstrap.js           # Axios config
└── views/
    ├── layouts/
    │   ├── app.blade.php      # Layout autenticado (solo @vite, sin CDN)
    │   └── guest.blade.php    # Layout público (solo @vite, sin CDN)
    └── livewire/
        ├── auth/
        ├── channels/
        ├── chat/
        │   ├── index.blade.php
        │   ├── notification-badge.blade.php
        │   ├── contact-info.blade.php
        │   └── quick-actions.blade.php
        ├── dashboard.blade.php
        └── settings/

routes/
├── web.php                    # Rutas web con middleware de permisos
└── console.php                # Comandos Artisan

database/
├── migrations/
├── seeders/
│   ├── DatabaseSeeder.php
│   └── RolesAndPermissionsSeeder.php
└── factories/

config/
└── services.php               # Configuración Evolution API
```

## Patrones de Arquitectura

### Componentes Livewire
- Full-page components con atributo `#[Layout('layouts.xxx')]`
- Vistas en `resources/views/livewire/` reflejando namespace
- Usar `#[Rule]` para validación inline
- Usar `#[Title]` para títulos de página
- Usar `#[On('event')]` para escuchar eventos
- Usar `$this->dispatch()` para emitir eventos
- Usar `#[Url]` para parámetros en URL (search, sort, etc.)

### Sistema de Permisos en Componentes
```php
// En mount() verificar permisos para acciones
public bool $canCreate = false;
public bool $canEdit = false;
public bool $canDelete = false;

public function mount(): void
{
    $user = auth()->user();
    $isAdmin = $user->hasRole('admin');
    
    $this->canCreate = $isAdmin || $user->hasPermission('modulo.crear');
    $this->canEdit = $isAdmin || $user->hasPermission('modulo.editar');
    $this->canDelete = $isAdmin || $user->hasPermission('modulo.eliminar');
}
```

### Layouts
- `layouts.app` - Usuarios autenticados (sidebar con menú dinámico según permisos)
- `layouts.guest` - Usuarios no autenticados (card centrada)

### Rutas con Permisos
```php
Route::middleware('auth')->group(function () {
    Route::get('/ruta', Componente::class)
        ->name('nombre.ruta')
        ->middleware('permission:modulo.accion');
});
```

### Convenciones de Nombres
- Componentes Livewire: PascalCase (ej: `Dashboard`, `Login`)
- Vistas: kebab-case (ej: `dashboard.blade.php`)
- Rutas: kebab-case con nombres descriptivos
- Modelos: Singular PascalCase (ej: `Channel`, `User`)
- Permisos: `modulo.accion` (ej: `usuarios.crear`, `roles.editar`)

### Notificaciones
- Toast notifications con SweetAlert2 (fondo blanco)
- Dispatch desde PHP: `$this->dispatch('toast', type: 'success', message: 'Mensaje')`
- Confirmaciones de eliminación con SweetAlert2 (fondo blanco)
- Evento `confirm-delete` para confirmaciones

### Relaciones de Modelos
- User belongsToMany Role (pivot: role_user)
- User belongsToMany Channel (pivot: channel_user)
- Role belongsToMany Permission (pivot: permission_role)
- Permission belongsTo Module
- Channel belongsToMany User

### Métodos de Usuario para Permisos
```php
$user->hasRole('admin');           // Verificar rol
$user->hasPermission('roles.ver'); // Verificar permiso
$user->assignRole($role);          // Asignar rol
$user->removeRole($role);          // Remover rol
```

## Creación de Nuevos Módulos

### Checklist al crear un módulo nuevo:
1. Crear migraciones necesarias
2. Crear modelos con relaciones
3. Crear componentes Livewire en `app/Livewire/NombreModulo/`
4. Crear vistas en `resources/views/livewire/nombre-modulo/`
5. Agregar rutas en `routes/web.php` con middleware de permisos
6. **IMPORTANTE**: Agregar módulo en `SyncModulesAndPermissions.php`
7. Ejecutar `php artisan permissions:sync`
8. Agregar enlace en sidebar (`layouts/app.blade.php`)

### Agregar módulo con permisos estándar (CRUD):
```php
// En app/Console/Commands/SyncModulesAndPermissions.php
// Agregar al array $standardModules:
['name' => 'nuevo_modulo', 'display_name' => 'Nuevo Módulo', 'icon' => 'icon-name', 'order' => 9],
```

### Agregar módulo con permisos personalizados:
```php
// En app/Console/Commands/SyncModulesAndPermissions.php
// Agregar al array $customModules:
'nuevo_modulo' => [
    'module' => ['name' => 'nuevo_modulo', 'display_name' => 'Nuevo Módulo', 'icon' => 'icon', 'order' => 9],
    'permissions' => [
        ['action' => 'ver', 'display_name' => 'Ver Módulo'],
        ['action' => 'accion_especial', 'display_name' => 'Acción Especial'],
    ],
],
```

### Después de agregar el módulo:
```bash
php artisan permissions:sync
```
Los permisos aparecerán automáticamente en la UI de edición de roles.
