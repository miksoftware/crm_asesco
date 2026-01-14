# Tech Stack

## Backend
- PHP 8.2+
- Laravel 12.x
- Livewire 3.x (full-page components, incluye Alpine.js internamente)
- MySQL database (Laragon)

## Frontend
- Tailwind CSS 4.x (via Vite + @tailwindcss/vite plugin)
- Alpine.js 3.x (incluido en Livewire 3, NO importar por separado)
- SweetAlert2 (notificaciones y confirmaciones, via CDN)
- Inter font family (Google Fonts via Bunny)

## IMPORTANTE: Configuración Frontend
- NO usar CDN de Tailwind (`cdn.tailwindcss.com`)
- NO usar `tailwind.config = {...}` en JavaScript
- NO importar Alpine desde npm (Livewire 3 ya lo incluye)
- Colores y estilos personalizados van en `resources/css/app.css` usando `@theme`
- Plugins de Alpine se registran en evento `livewire:init`

## Integraciones
- Evolution API v2 (WhatsApp)
  - Endpoints: createInstance, connectInstance, getInstance, deleteInstance, etc.
  - Autenticación via API Key en header

## Build System
- Vite 7.x con laravel-vite-plugin
- npm para dependencias frontend
- Composer para dependencias PHP

## Testing
- PHPUnit 11.x
- Laravel Pint para formateo de código

## Comandos Comunes

```bash
# Setup inicial
composer setup

# Desarrollo (servidor, queue, logs, vite)
composer dev

# Ejecutar tests
composer test

# Build assets frontend
npm run build

# Desarrollo frontend (hot reload)
npm run dev

# Migraciones
php artisan migrate

# Seeders
php artisan db:seed
php artisan db:seed --class=RolesAndPermissionsSeeder

# Sincronizar módulos y permisos (IMPORTANTE al crear nuevos módulos)
php artisan permissions:sync

# Formateo de código
./vendor/bin/pint

# Limpiar caché (importante después de cambios en middleware/rutas)
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## Dependencias Clave
- livewire/livewire: Componentes UI reactivos
- laravel/pint: Code style fixer
- laravel/pail: Real-time log viewer
- guzzlehttp/guzzle: HTTP client (Evolution API)

## Configuración Base de Datos
- Driver: MySQL
- Database: crm_asesco
- Host: 127.0.0.1
- Puerto: 3306

## Configuración Regional
- Timezone: America/Bogota
- Locale: es
- Faker locale: es_ES

## Variables de Entorno Importantes
```env
APP_NAME="ASESCO BPO"
APP_TIMEZONE=America/Bogota
APP_LOCALE=es

DB_CONNECTION=mysql
DB_DATABASE=crm_asesco
DB_HOST=127.0.0.1
DB_PORT=3306

EVOLUTION_API_URL=http://localhost:8080
EVOLUTION_API_KEY=tu_api_key_aqui
```

## Estilos y Colores
- Primary: Orange (#f97316)
- Secondary: Pink (#ec4899)
- Gradient principal: `linear-gradient(135deg, #f97316, #ea580c, #db2777, #ec4899)`
- Sidebar: Dark gray gradient (#1f2937 → #111827)
- Clase CSS: `.gradient-bg` para gradiente principal
- Clase CSS: `.sidebar-gradient` para sidebar

## Middleware de Permisos
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'permission' => \App\Http\Middleware\CheckPermission::class,
    ]);
})
```

## Estructura de Permisos
Formato: `modulo.accion`

Módulos disponibles:
- dashboard
- canales
- chats (permisos especiales: ver, enviar, etiquetas)
- usuarios
- roles
- clientes (pendiente)
- cobranzas (pendiente)
- reportes (pendiente)

Acciones estándar:
- ver
- crear
- editar
- eliminar

Ejemplos: `dashboard.ver`, `usuarios.crear`, `roles.editar`, `canales.eliminar`, `chats.enviar`

## Gestión de Módulos y Permisos

### Comando de sincronización
```bash
php artisan permissions:sync
```
Este comando sincroniza módulos y permisos sin eliminar datos existentes. Asigna automáticamente nuevos permisos al rol admin.

### Al crear un nuevo módulo:
1. Agregar el módulo en `app/Console/Commands/SyncModulesAndPermissions.php`
2. Ejecutar `php artisan permissions:sync`
3. Los permisos aparecerán automáticamente en la UI de roles

### Tipos de módulos:
- **Estándar**: Permisos CRUD (ver, crear, editar, eliminar)
- **Personalizados**: Permisos específicos (ej: chats tiene ver, enviar, etiquetas)

## Evolution API Service
```php
// Métodos disponibles en EvolutionApiService
$api->createInstance($name);           // Crear instancia
$api->connectInstance($name);          // Conectar y obtener QR
$api->getInstance($name);              // Obtener info de instancia
$api->getAllInstances();               // Listar todas las instancias
$api->deleteInstance($name);           // Eliminar instancia
$api->disconnectInstance($name);       // Desconectar instancia
$api->restartInstance($name);          // Reiniciar instancia
$api->getConnectionState($name);       // Estado de conexión
```

## UI Components Patterns

### Tablas con paginación
- Búsqueda con debounce 300ms
- Select de items por página (10, 25, 50)
- Ordenamiento por columnas clickeables

### Botones de acción
- Editar: `text-blue-600 hover:bg-blue-50`
- Eliminar: `text-red-600 hover:bg-red-50`
- Conectar: `text-green-600 bg-green-50`
- Desconectar: `text-red-600 bg-red-50`

### Estados de canal
- Conectado: verde (#22c55e)
- Desconectado: rojo (#ef4444)
- Conectando: amarillo (#f59e0b)
- QR Code: azul (#3b82f6)
