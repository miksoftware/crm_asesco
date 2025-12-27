# Tech Stack

## Backend
- PHP 8.2+
- Laravel 12.x
- Livewire 3.x (full-page components)
- MySQL database (Laragon)

## Frontend
- Tailwind CSS 4.x (via CDN)
- Alpine.js 3.x (via CDN)
- SweetAlert2 (notificaciones y confirmaciones)
- Inter font family

## Integraciones
- Evolution API v2 (WhatsApp)
  - URL: http://localhost:8080
  - API Key en .env: EVOLUTION_API_KEY

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

# Formateo de código
./vendor/bin/pint

# Limpiar caché
php artisan config:clear
php artisan cache:clear
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
```
APP_TIMEZONE=America/Bogota
APP_LOCALE=es

DB_CONNECTION=mysql
DB_DATABASE=crm_asesco

EVOLUTION_API_URL=http://localhost:8080
EVOLUTION_API_KEY=1234
```

## Estilos y Colores
- Primary: Orange (#f97316)
- Secondary: Pink (#ec4899)
- Gradient: linear-gradient(135deg, #f97316, #ea580c, #db2777, #ec4899)
- Sidebar: Dark gray gradient (#1f2937 → #111827)
