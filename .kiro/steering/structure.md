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
│   ├── Message.php            # Mensaje de chat
│   ├── Label.php              # Etiqueta para contactos
│   └── ChatNotification.php   # Notificación de chat
├── Providers/                 # Service providers
└── Services/
    ├── EvolutionApiService.php # Cliente HTTP para Evolution API
    ├── MessageService.php      # Servicio de mensajería
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
    │   ├── app.blade.php      # Layout autenticado (sidebar dinámico)
    │   └── guest.blade.php    # Layout público (card centrada)
    └── livewire/

routes/
├── web.php                    # Rutas web con middleware de permisos
├── api.php                    # Rutas API (webhooks)
└── console.php                # Comandos Artisan

database/
├── migrations/
├── seeders/
│   ├── DatabaseSeeder.php     # Seeder principal (solo para setup inicial)
│   ├── AdminSeeder.php        # Crear usuario admin
│   └── RolesAndPermissionsSeeder.php  # SOLO para setup inicial
└── factories/

config/
└── services.php               # Configuración Evolution API
```

## Sistema de Permisos y Módulos

### IMPORTANTE: Gestión de Permisos en Producción

**NO usar seeders para agregar nuevos módulos/permisos en producción.**

El comando `php artisan permissions:sync` es la forma correcta de gestionar permisos:
- Agrega módulos y permisos nuevos sin eliminar existentes
- Asigna automáticamente nuevos permisos al rol admin
- Es seguro ejecutar en producción (idempotente)
- No requiere `migrate:fresh`

### Agregar un Nuevo Módulo

1. **Editar el comando** `app/Console/Commands/SyncModulesAndPermissions.php`

2. **Para módulo con permisos CRUD estándar** (ver, crear, editar, eliminar):
```php
// Agregar al array $standardModules:
['name' => 'clientes', 'display_name' => 'Clientes', 'icon' => 'users', 'order' => 7],
```

3. **Para módulo con permisos personalizados**:
```php
// Agregar al array $customModules:
'reportes' => [
    'module' => ['name' => 'reportes', 'display_name' => 'Reportes', 'icon' => 'chart-bar', 'order' => 8],
    'permissions' => [
        ['action' => 'ver', 'display_name' => 'Ver Reportes'],
        ['action' => 'exportar', 'display_name' => 'Exportar Reportes'],
        ['action' => 'programar', 'display_name' => 'Programar Reportes'],
    ],
],
```

4. **Ejecutar el comando**:
```bash
php artisan permissions:sync
```

### Flujo de Trabajo para Nuevos Módulos

```
1. Crear migración(es) para tablas nuevas
2. Crear modelo(s) con relaciones
3. Agregar módulo en SyncModulesAndPermissions.php
4. Ejecutar: php artisan migrate
5. Ejecutar: php artisan permissions:sync
6. Crear componentes Livewire
7. Crear vistas blade
8. Agregar rutas con middleware de permisos
9. Agregar enlace en sidebar (layouts/app.blade.php)
```

### Seeders - Cuándo Usarlos

| Seeder | Uso | Producción |
|--------|-----|------------|
| `AdminSeeder` | Crear usuario admin inicial | Solo 1 vez en setup |
| `RolesAndPermissionsSeeder` | Setup inicial de roles | Solo 1 vez en setup |
| `LabelsSeeder` | Etiquetas predeterminadas | Solo 1 vez en setup |

**Para agregar permisos nuevos en producción: SIEMPRE usar `php artisan permissions:sync`**

## Patrones de Arquitectura

### Componentes Livewire
- Full-page components con atributo `#[Layout('layouts.xxx')]`
- Vistas en `resources/views/livewire/` reflejando namespace
- Usar `#[Rule]` para validación inline
- Usar `#[Title]` para títulos de página
- Usar `#[On('event')]` para escuchar eventos
- Usar `$this->dispatch()` para emitir eventos
- Usar `#[Url]` para parámetros en URL (search, sort, etc.)
- Usar `#[Computed]` para propiedades calculadas con cache

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
- Migraciones: `YYYY_MM_DD_HHMMSS_descripcion.php`

### Notificaciones
- Toast notifications con SweetAlert2 (fondo blanco con backdrop blur)
- Dispatch desde PHP: `$this->dispatch('toast', type: 'success', message: 'Mensaje')`
- Confirmaciones de eliminación con SweetAlert2
- Modales con `@teleport('body')` y `bg-black/30 backdrop-blur-sm`

### Relaciones de Modelos
- User belongsToMany Role (pivot: role_user)
- User belongsToMany Channel (pivot: channel_user)
- Role belongsToMany Permission (pivot: permission_role)
- Permission belongsTo Module
- Contact belongsToMany Label (pivot: contact_label)
- Contact hasMany Message

### Métodos de Usuario para Permisos
```php
$user->hasRole('admin');           // Verificar rol
$user->hasPermission('roles.ver'); // Verificar permiso
$user->assignRole($role);          // Asignar rol
$user->removeRole($role);          // Remover rol
```

## Comandos Importantes

```bash
# Sincronizar módulos y permisos (SEGURO en producción)
php artisan permissions:sync

# Configurar webhooks de canales
php artisan channels:setup-webhooks

# Importar chats desde Evolution API
php artisan chats:import --channel=1 --limit=500

# Limpiar caché (después de cambios en config/rutas)
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```
