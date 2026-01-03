<div class="space-y-6">
    <div class="flex gap-6">
        <!-- Sidebar de navegación -->
        <div class="w-64 flex-shrink-0">
            <div class="bg-white rounded-xl shadow-sm p-4 sticky top-24">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Contenido</h3>
                <nav class="space-y-1">
                    @foreach($sections as $key => $label)
                        <button wire:click="setSection('{{ $key }}')" 
                            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-all {{ $activeSection === $key ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-primary-600 font-medium border-l-4 border-primary-500' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="flex-1">
            <div class="bg-white rounded-xl shadow-sm p-8">
                {{-- Descripción General --}}
                @if($activeSection === 'overview')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">ASESCO BPO - Manual Técnico</h1>
                    <p class="text-gray-500 text-lg mb-8">Sistema de Gestión de Procesos de Negocio</p>

                    <div class="bg-gradient-to-r from-primary-50 to-secondary-50 rounded-xl p-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-800 mt-0">¿Qué es ASESCO BPO?</h2>
                        <p class="text-gray-600 mb-0">
                            ASESCO BPO es un sistema de gestión de procesos de negocio (Business Process Outsourcing) 
                            desarrollado para <strong>"Asesorías Especializadas y Cobranzas"</strong>. Es una aplicación web 
                            interna diseñada para gestionar cobranzas y servicios de asesoría a clientes, con integración 
                            de WhatsApp para comunicación directa.
                        </p>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800">Propósito del Sistema</h2>
                    <p class="text-gray-600">
                        El sistema permite a los empleados y administradores de ASESCO gestionar de manera eficiente:
                    </p>
                    <ul class="list-disc list-inside text-gray-600 space-y-2">
                        <li><strong>Canales de WhatsApp:</strong> Conexión y gestión de múltiples líneas de WhatsApp mediante Evolution API</li>
                        <li><strong>Usuarios:</strong> Administración de empleados con roles y permisos personalizados</li>
                        <li><strong>Roles y Permisos:</strong> Control de acceso granular por módulos y acciones</li>
                        <li><strong>Clientes:</strong> Gestión de cartera de clientes (en desarrollo)</li>
                        <li><strong>Cobranzas:</strong> Seguimiento de cobros y pagos (en desarrollo)</li>
                        <li><strong>Reportes:</strong> Informes y estadísticas del negocio (en desarrollo)</li>
                    </ul>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Arquitectura del Sistema</h2>
                    <p class="text-gray-600">
                        El sistema está construido siguiendo el patrón <strong>Full-Page Livewire Components</strong>, 
                        donde cada página es un componente Livewire independiente que maneja su propia lógica y vista.
                    </p>

                    <div class="bg-gray-900 rounded-xl p-6 my-6">
                        <p class="text-gray-400 text-sm mb-3 font-mono">Estructura de directorios principal:</p>
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>app/
├── Http/Middleware/          # Middleware de permisos
├── Livewire/                 # Componentes Livewire (lógica)
│   ├── Auth/                 # Login, Logout
│   ├── Channels/             # Gestión de canales WhatsApp
│   ├── Help/                 # Ayuda y documentación
│   └── Settings/             # Usuarios y Roles
├── Models/                   # Modelos Eloquent
└── Services/                 # Servicios (Evolution API)

resources/views/
├── layouts/                  # Layouts (app, guest)
└── livewire/                 # Vistas de componentes</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Usuarios Objetivo</h2>
                    <p class="text-gray-600">
                        El sistema está diseñado para el personal interno de ASESCO:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                            <h4 class="font-semibold text-red-700 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                Administradores
                            </h4>
                            <p class="text-red-600 text-sm mt-1">Acceso total al sistema, gestión de usuarios, roles y configuración general.</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <h4 class="font-semibold text-blue-700 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Usuarios
                            </h4>
                            <p class="text-blue-600 text-sm mt-1">Acceso limitado según permisos asignados, operaciones de cobranza y atención.</p>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Configuración Regional</h2>
                    <div class="bg-gray-50 rounded-lg p-4 mt-4">
                        <ul class="space-y-2 text-gray-600">
                            <li><strong>Zona horaria:</strong> America/Bogota (UTC-5)</li>
                            <li><strong>Idioma:</strong> Español (es)</li>
                            <li><strong>Locale Faker:</strong> es_ES</li>
                            <li><strong>Formato de fecha:</strong> DD/MM/YYYY</li>
                        </ul>
                    </div>
                </div>
                @endif

                {{-- Stack Tecnológico --}}
                @if($activeSection === 'stack')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Stack Tecnológico</h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- Backend -->
                        <div class="bg-purple-50 rounded-xl p-6 border border-purple-200">
                            <h3 class="text-lg font-semibold text-purple-700 mt-0 flex items-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                </svg>
                                Backend
                            </h3>
                            <ul class="space-y-2 text-purple-600 text-sm">
                                <li><strong>PHP 8.2+</strong> - Lenguaje de programación</li>
                                <li><strong>Laravel 12.x</strong> - Framework PHP</li>
                                <li><strong>Livewire 3.x</strong> - Componentes reactivos</li>
                                <li><strong>MySQL</strong> - Base de datos</li>
                            </ul>
                        </div>

                        <!-- Frontend -->
                        <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
                            <h3 class="text-lg font-semibold text-blue-700 mt-0 flex items-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Frontend
                            </h3>
                            <ul class="space-y-2 text-blue-600 text-sm">
                                <li><strong>Tailwind CSS 4.x</strong> - Framework CSS (CDN)</li>
                                <li><strong>Alpine.js 3.x</strong> - Interactividad (CDN)</li>
                                <li><strong>SweetAlert2</strong> - Notificaciones</li>
                                <li><strong>Inter</strong> - Tipografía (Google Fonts)</li>
                            </ul>
                        </div>

                        <!-- Integraciones -->
                        <div class="bg-green-50 rounded-xl p-6 border border-green-200">
                            <h3 class="text-lg font-semibold text-green-700 mt-0 flex items-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                Integraciones
                            </h3>
                            <ul class="space-y-2 text-green-600 text-sm">
                                <li><strong>Evolution API v2</strong> - WhatsApp</li>
                                <li><strong>UI Avatars</strong> - Avatares por defecto</li>
                                <li><strong>Guzzle HTTP</strong> - Cliente HTTP</li>
                            </ul>
                        </div>

                        <!-- Herramientas -->
                        <div class="bg-orange-50 rounded-xl p-6 border border-orange-200">
                            <h3 class="text-lg font-semibold text-orange-700 mt-0 flex items-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Herramientas
                            </h3>
                            <ul class="space-y-2 text-orange-600 text-sm">
                                <li><strong>Vite 7.x</strong> - Build tool</li>
                                <li><strong>Composer</strong> - Dependencias PHP</li>
                                <li><strong>NPM</strong> - Dependencias JS</li>
                                <li><strong>Laravel Pint</strong> - Code style</li>
                            </ul>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800">Dependencias PHP (composer.json)</h2>
                    <div class="bg-gray-900 rounded-xl p-6 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^12.0",
        "laravel/tinker": "^2.10.1",
        "livewire/livewire": "^3.7"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pail": "^1.2.2",
        "laravel/pint": "^1.24",
        "phpunit/phpunit": "^11.5.3"
    }
}</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Colores del Sistema</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                        <div class="text-center">
                            <div class="w-full h-16 rounded-lg bg-gradient-to-r from-orange-500 to-pink-500 mb-2"></div>
                            <p class="text-sm text-gray-600">Gradiente Principal</p>
                        </div>
                        <div class="text-center">
                            <div class="w-full h-16 rounded-lg bg-orange-500 mb-2"></div>
                            <p class="text-sm text-gray-600">Primary (#f97316)</p>
                        </div>
                        <div class="text-center">
                            <div class="w-full h-16 rounded-lg bg-pink-500 mb-2"></div>
                            <p class="text-sm text-gray-600">Secondary (#ec4899)</p>
                        </div>
                        <div class="text-center">
                            <div class="w-full h-16 rounded-lg bg-gradient-to-b from-gray-800 to-gray-900 mb-2"></div>
                            <p class="text-sm text-gray-600">Sidebar</p>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Requisitos --}}
                @if($activeSection === 'requirements')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Requisitos del Sistema</h1>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                        <p class="text-yellow-700 text-sm flex items-center gap-2 mb-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Asegúrate de cumplir con todos los requisitos antes de iniciar la instalación.
                        </p>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800">Requisitos de Software</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 mt-4">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Software</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Versión Mínima</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recomendada</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">PHP</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">8.2</td>
                                    <td class="px-4 py-3 text-sm text-green-600 font-medium">8.3+</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">MySQL</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">8.0</td>
                                    <td class="px-4 py-3 text-sm text-green-600 font-medium">8.0+</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">Node.js</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">18.x</td>
                                    <td class="px-4 py-3 text-sm text-green-600 font-medium">20.x+</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">Composer</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">2.5</td>
                                    <td class="px-4 py-3 text-sm text-green-600 font-medium">2.7+</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">NPM</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">9.x</td>
                                    <td class="px-4 py-3 text-sm text-green-600 font-medium">10.x+</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Extensiones PHP Requeridas</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                        @foreach(['BCMath', 'Ctype', 'Fileinfo', 'JSON', 'Mbstring', 'OpenSSL', 'PDO', 'PDO_MySQL', 'Tokenizer', 'XML', 'cURL', 'GD'] as $ext)
                        <div class="bg-gray-100 rounded-lg px-3 py-2 text-sm text-gray-700 flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $ext }}
                        </div>
                        @endforeach
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Requisitos de Hardware (Producción)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <h4 class="font-semibold text-gray-800">Mínimo</h4>
                            <ul class="text-sm text-gray-600 mt-2 space-y-1">
                                <li>• 1 CPU Core</li>
                                <li>• 1 GB RAM</li>
                                <li>• 20 GB SSD</li>
                            </ul>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <h4 class="font-semibold text-green-700">Recomendado</h4>
                            <ul class="text-sm text-green-600 mt-2 space-y-1">
                                <li>• 2 CPU Cores</li>
                                <li>• 2 GB RAM</li>
                                <li>• 40 GB SSD</li>
                            </ul>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <h4 class="font-semibold text-blue-700">Óptimo</h4>
                            <ul class="text-sm text-blue-600 mt-2 space-y-1">
                                <li>• 4 CPU Cores</li>
                                <li>• 4 GB RAM</li>
                                <li>• 80 GB SSD</li>
                            </ul>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Entornos de Desarrollo Recomendados</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <h4 class="font-semibold text-blue-700">Windows</h4>
                            <p class="text-blue-600 text-sm mt-1">Laragon (recomendado), XAMPP, WAMP</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <h4 class="font-semibold text-gray-700">Linux/Mac</h4>
                            <p class="text-gray-600 text-sm mt-1">Laravel Valet, Docker, LAMP/MAMP</p>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Instalación --}}
                @if($activeSection === 'installation')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Instalación</h1>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <p class="text-blue-700 text-sm flex items-center gap-2 mb-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Sigue los pasos en orden. Cada paso depende del anterior.
                        </p>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800">Paso 1: Clonar el Repositorio</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Clonar el repositorio
git clone https://github.com/miksoftware/crm_asesco.git

# Entrar al directorio
cd crm_asesco</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Paso 2: Instalar Dependencias PHP</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Instalar dependencias de Composer
composer install</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Paso 3: Configurar Variables de Entorno</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Copiar archivo de ejemplo
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate</code></pre>
                    </div>
                    
                    <p class="text-gray-600">Edita el archivo <code class="bg-gray-100 px-2 py-1 rounded">.env</code> con tu configuración:</p>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>APP_NAME="ASESCO BPO"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=America/Bogota
APP_URL=http://localhost
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_asesco
DB_USERNAME=root
DB_PASSWORD=

# Evolution API (WhatsApp)
EVOLUTION_API_URL=http://localhost:8080
EVOLUTION_API_KEY=tu_api_key_aqui</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Paso 4: Crear Base de Datos</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># En MySQL, crear la base de datos
CREATE DATABASE crm_asesco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Paso 5: Ejecutar Migraciones y Seeders</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Ejecutar migraciones (crear tablas)
php artisan migrate

# Ejecutar seeders (datos iniciales)
php artisan db:seed</code></pre>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 my-4">
                        <p class="text-yellow-700 text-sm mb-0">
                            <strong>Importante:</strong> El seeder crea el usuario administrador con las credenciales:
                            <br>• Email: <code class="bg-yellow-100 px-1 rounded">admin@asesco.com</code>
                            <br>• Contraseña: <code class="bg-yellow-100 px-1 rounded">password</code>
                        </p>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Paso 6: Instalar Dependencias Frontend</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Instalar dependencias de NPM
npm install

# Compilar assets (desarrollo)
npm run dev

# O compilar para producción
npm run build</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Paso 7: Crear Enlace de Storage</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Crear enlace simbólico para archivos públicos
php artisan storage:link</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Paso 8: Iniciar el Servidor</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Opción 1: Comando rápido (servidor + vite + queue + logs)
composer dev

# Opción 2: Solo servidor PHP
php artisan serve</code></pre>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mt-6">
                        <p class="text-green-700 text-sm flex items-center gap-2 mb-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            ¡Listo! Accede a <strong>http://localhost:8000</strong> en tu navegador.
                        </p>
                    </div>
                </div>
                @endif

                {{-- Base de Datos --}}
                @if($activeSection === 'database')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Base de Datos</h1>
                    
                    <h2 class="text-xl font-semibold text-gray-800">Diagrama de Tablas</h2>
                    <p class="text-gray-600">El sistema utiliza las siguientes tablas principales:</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <!-- Users -->
                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                            <h4 class="font-semibold text-blue-700 mt-0">users</h4>
                            <ul class="text-sm text-blue-600 space-y-1">
                                <li>• id (bigint, PK)</li>
                                <li>• name (varchar)</li>
                                <li>• email (varchar, unique)</li>
                                <li>• password (varchar)</li>
                                <li>• profile_photo_path (varchar, nullable)</li>
                                <li>• remember_token (varchar)</li>
                                <li>• timestamps</li>
                            </ul>
                        </div>

                        <!-- Roles -->
                        <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                            <h4 class="font-semibold text-red-700 mt-0">roles</h4>
                            <ul class="text-sm text-red-600 space-y-1">
                                <li>• id (bigint, PK)</li>
                                <li>• name (varchar, unique)</li>
                                <li>• display_name (varchar)</li>
                                <li>• description (text, nullable)</li>
                                <li>• color (varchar, default: #6b7280)</li>
                                <li>• timestamps</li>
                            </ul>
                        </div>

                        <!-- Modules -->
                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                            <h4 class="font-semibold text-purple-700 mt-0">modules</h4>
                            <ul class="text-sm text-purple-600 space-y-1">
                                <li>• id (bigint, PK)</li>
                                <li>• name (varchar, unique)</li>
                                <li>• display_name (varchar)</li>
                                <li>• icon (varchar, nullable)</li>
                                <li>• order (integer, default: 0)</li>
                                <li>• timestamps</li>
                            </ul>
                        </div>

                        <!-- Permissions -->
                        <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                            <h4 class="font-semibold text-green-700 mt-0">permissions</h4>
                            <ul class="text-sm text-green-600 space-y-1">
                                <li>• id (bigint, PK)</li>
                                <li>• module_id (FK → modules)</li>
                                <li>• name (varchar, unique)</li>
                                <li>• display_name (varchar)</li>
                                <li>• action (varchar)</li>
                                <li>• timestamps</li>
                            </ul>
                        </div>

                        <!-- Channels -->
                        <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                            <h4 class="font-semibold text-orange-700 mt-0">channels</h4>
                            <ul class="text-sm text-orange-600 space-y-1">
                                <li>• id (bigint, PK)</li>
                                <li>• name (varchar)</li>
                                <li>• instance_name (varchar, unique)</li>
                                <li>• phone_number (varchar, nullable)</li>
                                <li>• status (varchar, default: disconnected)</li>
                                <li>• timestamps</li>
                            </ul>
                        </div>

                        <!-- Pivot Tables -->
                        <div class="bg-gray-50 rounded-xl p-4 border">
                            <h4 class="font-semibold text-gray-700 mt-0">Tablas Pivot</h4>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• <strong>role_user:</strong> user_id, role_id</li>
                                <li>• <strong>permission_role:</strong> permission_id, role_id</li>
                                <li>• <strong>channel_user:</strong> channel_id, user_id</li>
                            </ul>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Migraciones</h2>
                    <p class="text-gray-600">Las migraciones se encuentran en <code class="bg-gray-100 px-2 py-1 rounded">database/migrations/</code>:</p>
                    
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>database/migrations/
├── 0001_01_01_000000_create_users_table.php
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
├── 2025_12_22_200234_create_modules_table.php
├── 2025_12_22_200235_create_roles_table.php
├── 2025_12_22_200239_create_permissions_table.php
├── 2025_12_23_183743_create_channels_table.php
└── 2025_12_27_110208_add_profile_photo_to_users_table.php</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Comandos de Migraciones</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Ejecutar todas las migraciones pendientes
php artisan migrate

# Ver estado de las migraciones
php artisan migrate:status

# Revertir última migración
php artisan migrate:rollback

# Revertir todas las migraciones
php artisan migrate:reset

# Revertir y volver a ejecutar todas
php artisan migrate:refresh

# Eliminar todas las tablas y migrar de nuevo
php artisan migrate:fresh

# Migrar con seeders
php artisan migrate:fresh --seed</code></pre>
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mt-4">
                        <p class="text-red-700 text-sm flex items-center gap-2 mb-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <strong>Precaución:</strong> Los comandos <code>migrate:fresh</code> y <code>migrate:refresh</code> eliminan todos los datos.
                        </p>
                    </div>
                </div>
                @endif

                {{-- Seeders --}}
                @if($activeSection === 'seeders')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Seeders</h1>
                    
                    <p class="text-gray-600">Los seeders crean los datos iniciales necesarios para que el sistema funcione correctamente.</p>

                    <h2 class="text-xl font-semibold text-gray-800 mt-6">Seeders Disponibles</h2>
                    
                    <div class="space-y-4 mt-4">
                        <!-- AdminSeeder -->
                        <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                            <h4 class="font-semibold text-red-700 mt-0 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                AdminSeeder
                            </h4>
                            <p class="text-red-600 text-sm">Crea el usuario administrador inicial del sistema.</p>
                            <div class="bg-gray-900 rounded-lg p-3 mt-2">
                                <pre class="text-green-400 text-xs overflow-x-auto"><code>User::create([
    'name' => 'Administrador',
    'email' => 'admin@asesco.com',
    'password' => Hash::make('password'),
]);</code></pre>
                            </div>
                        </div>

                        <!-- RolesAndPermissionsSeeder -->
                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                            <h4 class="font-semibold text-purple-700 mt-0 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                RolesAndPermissionsSeeder
                            </h4>
                            <p class="text-purple-600 text-sm">Crea módulos, permisos y roles del sistema.</p>
                            
                            <h5 class="font-medium text-purple-700 mt-3 mb-2">Módulos creados:</h5>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach(['Dashboard', 'Canales', 'Usuarios', 'Roles', 'Clientes', 'Cobranzas', 'Reportes'] as $mod)
                                <span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded">{{ $mod }}</span>
                                @endforeach
                            </div>

                            <h5 class="font-medium text-purple-700 mt-3 mb-2">Acciones por módulo:</h5>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach(['ver', 'crear', 'editar', 'eliminar'] as $action)
                                <span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded">{{ $action }}</span>
                                @endforeach
                            </div>

                            <h5 class="font-medium text-purple-700 mt-3 mb-2">Roles creados:</h5>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                    <span class="text-sm"><strong>admin</strong> - Acceso total a todos los permisos</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                    <span class="text-sm"><strong>usuario</strong> - Acceso básico (dashboard, clientes, cobranzas)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Comandos para Ejecutar Seeders</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Ejecutar todos los seeders
php artisan db:seed

# Ejecutar seeder específico
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=RolesAndPermissionsSeeder

# Migrar y ejecutar seeders
php artisan migrate:fresh --seed</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Orden de Ejecución</h2>
                    <p class="text-gray-600">El <code class="bg-gray-100 px-2 py-1 rounded">DatabaseSeeder</code> ejecuta los seeders en este orden:</p>
                    
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        AdminSeeder::class,              // 1. Crear usuario admin
        RolesAndPermissionsSeeder::class, // 2. Crear módulos, permisos y roles
    ]);
}</code></pre>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mt-4">
                        <p class="text-yellow-700 text-sm flex items-center gap-2 mb-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <strong>Nota:</strong> El AdminSeeder debe ejecutarse ANTES que RolesAndPermissionsSeeder para que el rol admin se asigne al usuario.
                        </p>
                    </div>
                </div>
                @endif

                {{-- Sistema de Permisos --}}
                @if($activeSection === 'permissions')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Sistema de Permisos</h1>
                    
                    <p class="text-gray-600">El sistema implementa un control de acceso basado en roles (RBAC) con permisos granulares por módulo y acción.</p>

                    <h2 class="text-xl font-semibold text-gray-800 mt-6">Estructura de Permisos</h2>
                    <p class="text-gray-600">Los permisos siguen el formato: <code class="bg-gray-100 px-2 py-1 rounded">modulo.accion</code></p>
                    
                    <div class="overflow-x-auto mt-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Módulo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ver</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Crear</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Editar</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Eliminar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                @foreach(['dashboard', 'canales', 'usuarios', 'roles', 'clientes', 'cobranzas', 'reportes'] as $mod)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ ucfirst($mod) }}</td>
                                    <td class="px-4 py-2 text-gray-500"><code class="text-xs">{{ $mod }}.ver</code></td>
                                    <td class="px-4 py-2 text-gray-500"><code class="text-xs">{{ $mod }}.crear</code></td>
                                    <td class="px-4 py-2 text-gray-500"><code class="text-xs">{{ $mod }}.editar</code></td>
                                    <td class="px-4 py-2 text-gray-500"><code class="text-xs">{{ $mod }}.eliminar</code></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Middleware de Permisos</h2>
                    <p class="text-gray-600">Las rutas se protegen con el middleware <code class="bg-gray-100 px-2 py-1 rounded">permission</code>:</p>
                    
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>// routes/web.php
Route::get('/dashboard', Dashboard::class)
    ->name('dashboard')
    ->middleware('permission:dashboard.ver');

Route::get('/configuracion/usuarios', UsersIndex::class)
    ->name('settings.users.index')
    ->middleware('permission:usuarios.ver');</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Verificación en Componentes Livewire</h2>
                    <p class="text-gray-600">En los componentes, verifica permisos en el método <code class="bg-gray-100 px-2 py-1 rounded">mount()</code>:</p>
                    
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>// En el componente Livewire
public bool $canCreate = false;
public bool $canEdit = false;
public bool $canDelete = false;

public function mount(): void
{
    $user = auth()->user();
    $isAdmin = $user->hasRole('admin');
    
    $this->canCreate = $isAdmin || $user->hasPermission('usuarios.crear');
    $this->canEdit = $isAdmin || $user->hasPermission('usuarios.editar');
    $this->canDelete = $isAdmin || $user->hasPermission('usuarios.eliminar');
}</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Verificación en Vistas Blade</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>{{-- Mostrar botón solo si tiene permiso --}}
@@if($canCreate)
    &lt;a href="{{ route('settings.users.create') }}"&gt;
        Nuevo Usuario
    &lt;/a&gt;
@@endif

{{-- En el sidebar del layout --}}
@@if($isAdmin || ($user && $user->hasPermission('usuarios.ver')))
    &lt;a href="{{ route('settings.users.index') }}"&gt;Usuarios&lt;/a&gt;
@@endif</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Métodos del Modelo User</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>// Verificar si tiene un rol
$user->hasRole('admin');        // true/false

// Verificar si tiene un permiso
$user->hasPermission('usuarios.crear');  // true/false

// Asignar rol
$user->assignRole($role);       // Role model o nombre

// Remover rol
$user->removeRole($role);

// Obtener todos los permisos del usuario
$user->getAllPermissions();     // Collection</code></pre>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mt-4">
                        <p class="text-blue-700 text-sm flex items-center gap-2 mb-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <strong>Nota:</strong> Los usuarios con rol <code>admin</code> tienen acceso total automático a todas las funciones.
                        </p>
                    </div>
                </div>
                @endif

                {{-- Evolution API --}}
                @if($activeSection === 'evolution')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Evolution API</h1>
                    
                    <p class="text-gray-600">Evolution API es un servidor que permite conectar múltiples números de WhatsApp y gestionar mensajes mediante una API REST.</p>

                    <div class="bg-green-50 rounded-xl p-6 border border-green-200 my-6">
                        <h3 class="text-lg font-semibold text-green-700 mt-0 flex items-center gap-2">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            Evolution API v2
                        </h3>
                        <p class="text-green-600 mb-0">Repositorio oficial: <a href="https://github.com/EvolutionAPI/evolution-api" target="_blank" class="underline">github.com/EvolutionAPI/evolution-api</a></p>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800">Configuración</h2>
                    <p class="text-gray-600">Configura las variables en el archivo <code class="bg-gray-100 px-2 py-1 rounded">.env</code>:</p>
                    
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Evolution API
EVOLUTION_API_URL=http://localhost:8080
EVOLUTION_API_KEY=tu_api_key_aqui</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Servicio EvolutionApiService</h2>
                    <p class="text-gray-600">El sistema utiliza el servicio <code class="bg-gray-100 px-2 py-1 rounded">App\Services\EvolutionApiService</code> para comunicarse con Evolution API:</p>
                    
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>use App\Services\EvolutionApiService;

$api = app(EvolutionApiService::class);

// Crear una nueva instancia
$api->createInstance('nombre_instancia');

// Conectar y obtener código QR
$qr = $api->connectInstance('nombre_instancia');

// Obtener información de una instancia
$info = $api->getInstance('nombre_instancia');

// Listar todas las instancias
$instances = $api->getAllInstances();

// Obtener estado de conexión
$state = $api->getConnectionState('nombre_instancia');

// Desconectar instancia
$api->disconnectInstance('nombre_instancia');

// Reiniciar instancia
$api->restartInstance('nombre_instancia');

// Eliminar instancia
$api->deleteInstance('nombre_instancia');</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Estados de Conexión</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                        <div class="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                            <div class="w-4 h-4 rounded-full bg-green-500 mx-auto mb-2"></div>
                            <p class="text-sm font-medium text-green-700">Conectado</p>
                            <p class="text-xs text-green-600">open</p>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-lg border border-red-200">
                            <div class="w-4 h-4 rounded-full bg-red-500 mx-auto mb-2"></div>
                            <p class="text-sm font-medium text-red-700">Desconectado</p>
                            <p class="text-xs text-red-600">close</p>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                            <div class="w-4 h-4 rounded-full bg-yellow-500 mx-auto mb-2"></div>
                            <p class="text-sm font-medium text-yellow-700">Conectando</p>
                            <p class="text-xs text-yellow-600">connecting</p>
                        </div>
                        <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="w-4 h-4 rounded-full bg-blue-500 mx-auto mb-2"></div>
                            <p class="text-sm font-medium text-blue-700">Escanear QR</p>
                            <p class="text-xs text-blue-600">qrcode</p>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Instalar Evolution API con Docker</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># docker-compose.yml
version: '3'
services:
  evolution-api:
    image: atendai/evolution-api:latest
    ports:
      - "8080:8080"
    environment:
      - AUTHENTICATION_API_KEY=tu_api_key_aqui
    volumes:
      - evolution_data:/evolution/instances

volumes:
  evolution_data:</code></pre>
                    </div>

                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Iniciar Evolution API
docker-compose up -d</code></pre>
                    </div>
                </div>
                @endif

                {{-- Producción --}}
                @if($activeSection === 'production')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Despliegue en Producción</h1>
                    
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                        <p class="text-red-700 text-sm flex items-center gap-2 mb-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <strong>Importante:</strong> Nunca despliegues con APP_DEBUG=true en producción.
                        </p>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800">1. Configuración del Servidor</h2>
                    <p class="text-gray-600">Requisitos del servidor de producción:</p>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>PHP 8.2+ con extensiones requeridas</li>
                        <li>MySQL 8.0+</li>
                        <li>Nginx o Apache</li>
                        <li>SSL/HTTPS configurado</li>
                        <li>Composer instalado</li>
                    </ul>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">2. Variables de Entorno (.env)</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>APP_NAME="ASESCO BPO"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Bogota
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_asesco
DB_USERNAME=usuario_produccion
DB_PASSWORD=contraseña_segura

EVOLUTION_API_URL=https://api-whatsapp.tu-dominio.com
EVOLUTION_API_KEY=api_key_produccion</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">3. Comandos de Despliegue</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Clonar repositorio
git clone https://github.com/miksoftware/crm_asesco.git
cd crm_asesco

# Instalar dependencias (sin dev)
composer install --no-dev --optimize-autoloader

# Configurar entorno
cp .env.example .env
# Editar .env con valores de producción

# Generar clave
php artisan key:generate

# Ejecutar migraciones
php artisan migrate --force

# Ejecutar seeders (solo primera vez)
php artisan db:seed --force

# Compilar assets
npm install
npm run build

# Crear enlace de storage
php artisan storage:link

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Permisos de directorios
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">4. Configuración Nginx</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>server {
    listen 80;
    listen [::]:80;
    server_name tu-dominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name tu-dominio.com;
    root /var/www/crm_asesco/public;

    ssl_certificate /etc/letsencrypt/live/tu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tu-dominio.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">5. Actualizar en Producción</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Script de actualización
#!/bin/bash
cd /var/www/crm_asesco

# Modo mantenimiento
php artisan down

# Obtener cambios
git pull origin main

# Actualizar dependencias
composer install --no-dev --optimize-autoloader

# Migraciones
php artisan migrate --force

# Compilar assets
npm install
npm run build

# Limpiar y optimizar caché
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Salir de mantenimiento
php artisan up</code></pre>
                    </div>
                </div>
                @endif

                {{-- Comandos --}}
                @if($activeSection === 'commands')
                <div class="prose prose-orange max-w-none">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">Comandos Útiles</h1>
                    
                    <h2 class="text-xl font-semibold text-gray-800">Desarrollo</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Iniciar servidor de desarrollo completo (server + vite + queue + logs)
composer dev

# Solo servidor PHP
php artisan serve

# Solo Vite (hot reload)
npm run dev

# Ejecutar tests
composer test
php artisan test

# Formatear código con Pint
./vendor/bin/pint</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Base de Datos</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Ejecutar migraciones
php artisan migrate

# Ver estado de migraciones
php artisan migrate:status

# Revertir última migración
php artisan migrate:rollback

# Resetear y migrar de nuevo
php artisan migrate:fresh

# Migrar con seeders
php artisan migrate:fresh --seed

# Ejecutar seeders
php artisan db:seed
php artisan db:seed --class=RolesAndPermissionsSeeder</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Caché y Optimización</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Limpiar todas las cachés
php artisan optimize:clear

# Limpiar caché individual
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Crear caché (producción)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Livewire</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Crear componente Livewire
php artisan make:livewire NombreComponente

# Crear en subdirectorio
php artisan make:livewire Settings/Users/Index

# Publicar configuración
php artisan livewire:publish --config</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Generadores</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Crear modelo con migración, factory y seeder
php artisan make:model NombreModelo -mfs

# Crear controlador
php artisan make:controller NombreController

# Crear middleware
php artisan make:middleware NombreMiddleware

# Crear seeder
php artisan make:seeder NombreSeeder

# Crear migración
php artisan make:migration create_tabla_table

# Crear factory
php artisan make:factory NombreFactory</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Utilidades</h2>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Consola interactiva (Tinker)
php artisan tinker

# Ver rutas registradas
php artisan route:list

# Crear enlace simbólico de storage
php artisan storage:link

# Modo mantenimiento
php artisan down
php artisan up

# Ver logs en tiempo real
php artisan pail</code></pre>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8">Composer Scripts</h2>
                    <p class="text-gray-600">Scripts personalizados definidos en <code class="bg-gray-100 px-2 py-1 rounded">composer.json</code>:</p>
                    <div class="bg-gray-900 rounded-xl p-4 my-4">
                        <pre class="text-green-400 text-sm overflow-x-auto"><code># Setup inicial completo
composer setup

# Desarrollo (servidor + queue + logs + vite)
composer dev

# Ejecutar tests
composer test</code></pre>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
