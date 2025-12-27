# Project Structure

## Estructura de Directorios

```
app/
├── Http/Controllers/          # Controladores tradicionales (uso mínimo)
├── Livewire/                  # Componentes Livewire (patrón principal)
│   ├── Auth/                  # Autenticación (Login, Logout)
│   ├── Channels/              # Canales WhatsApp (Index, Create, Edit)
│   └── Settings/              # Configuración
│       ├── Users/             # CRUD Usuarios
│       └── Roles/             # CRUD Roles
├── Models/                    # Modelos Eloquent
│   ├── User.php               # Usuario con roles y canales
│   ├── Role.php               # Rol con permisos
│   ├── Permission.php         # Permiso individual
│   ├── Module.php             # Módulo del sistema
│   └── Channel.php            # Canal WhatsApp
├── Providers/                 # Service providers
└── Services/
    └── EvolutionApiService.php # Cliente API Evolution

resources/
├── css/                       # Tailwind CSS source
├── js/                        # JavaScript entry points
└── views/
    ├── layouts/
    │   ├── app.blade.php      # Layout autenticado (sidebar)
    │   └── guest.blade.php    # Layout público (login)
    └── livewire/              # Vistas de componentes
        ├── auth/
        ├── channels/
        ├── settings/
        │   ├── users/
        │   └── roles/
        └── dashboard.blade.php

routes/
├── web.php                    # Rutas web (Livewire components)
└── console.php                # Comandos Artisan

database/
├── migrations/                # Migraciones de esquema
├── seeders/
│   ├── DatabaseSeeder.php
│   └── RolesAndPermissionsSeeder.php
└── factories/                 # Factories para testing

config/
└── services.php               # Configuración Evolution API
```

## Patrones de Arquitectura

### Componentes Livewire
- Full-page components con atributo `#[Layout('layouts.xxx')]`
- Vistas en `resources/views/livewire/` reflejando namespace
- Usar `#[Rule]` para validación
- Usar `#[Title]` para títulos de página
- Usar `#[On('event')]` para escuchar eventos
- Usar `$this->dispatch()` para emitir eventos

### Layouts
- `layouts.app` - Usuarios autenticados (navegación sidebar)
- `layouts.guest` - Usuarios no autenticados (card centrada)

### Rutas
- Rutas apuntan directamente a componentes Livewire
- Agrupadas por middleware: `guest` y `auth`
- Prefijos: `/configuracion/` para settings, `/canales/` para channels

### Convenciones de Nombres
- Componentes Livewire: PascalCase (ej: `Dashboard`, `Login`)
- Vistas: kebab-case (ej: `dashboard.blade.php`)
- Rutas: kebab-case con nombres descriptivos
- Modelos: Singular PascalCase (ej: `Channel`, `User`)

### Notificaciones
- Toast notifications con SweetAlert2
- Fondo blanco, texto normal
- Dispatch desde PHP: `$this->dispatch('toast', type: 'success', message: 'Mensaje')`
- Confirmaciones de eliminación con SweetAlert2

### Relaciones de Modelos
- User belongsToMany Role (pivot: role_user)
- User belongsToMany Channel (pivot: channel_user)
- Role belongsToMany Permission (pivot: permission_role)
- Permission belongsTo Module
- Channel belongsToMany User
