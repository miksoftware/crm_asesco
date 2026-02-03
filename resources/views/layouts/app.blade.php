<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} - {{ config('app.name', 'ASESCO BPO') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased bg-gray-100" x-data="{ 
    sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false',
    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
        localStorage.setItem('sidebarOpen', this.sidebarOpen);
    }
}">

    @php
        $currentRoute = request()->route()->getName();
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        
        // Breadcrumb mapping
        $breadcrumbs = [
            'dashboard' => ['title' => 'Dashboard', 'parent' => null],
            'channels.index' => ['title' => 'Canales', 'parent' => null],
            'channels.create' => ['title' => 'Crear Canal', 'parent' => 'channels.index'],
            'channels.edit' => ['title' => 'Editar Canal', 'parent' => 'channels.index'],
            'chat.index' => ['title' => 'Chat', 'parent' => null],
            'campaigns.index' => ['title' => 'Mensajería Masiva', 'parent' => null],
            'campaigns.create' => ['title' => 'Nueva Campaña', 'parent' => 'campaigns.index'],
            'campaigns.results' => ['title' => 'Resultados', 'parent' => 'campaigns.index'],
            'settings.users.index' => ['title' => 'Usuarios', 'parent' => null, 'section' => 'Configuración'],
            'settings.users.create' => ['title' => 'Crear Usuario', 'parent' => 'settings.users.index', 'section' => 'Configuración'],
            'settings.users.edit' => ['title' => 'Editar Usuario', 'parent' => 'settings.users.index', 'section' => 'Configuración'],
            'settings.roles.index' => ['title' => 'Roles', 'parent' => null, 'section' => 'Configuración'],
            'settings.roles.create' => ['title' => 'Crear Rol', 'parent' => 'settings.roles.index', 'section' => 'Configuración'],
            'settings.roles.edit' => ['title' => 'Editar Rol', 'parent' => 'settings.roles.index', 'section' => 'Configuración'],
            'settings.labels.index' => ['title' => 'Etiquetas', 'parent' => null, 'section' => 'Configuración'],
            'reports.chats-attended' => ['title' => 'Chats Atendidos', 'parent' => null, 'section' => 'Reportes'],
            'help.technical-manual' => ['title' => 'Manual Técnico', 'parent' => null, 'section' => 'Ayuda'],
        ];
        
        $currentBreadcrumb = $breadcrumbs[$currentRoute] ?? ['title' => 'Dashboard', 'parent' => null];
        $pageTitle = $currentBreadcrumb['title'];
        $parentRoute = $currentBreadcrumb['parent'] ?? null;
        $section = $currentBreadcrumb['section'] ?? null;
    @endphp

    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="sidebar-gradient min-h-screen flex flex-col fixed left-0 top-0 z-40 transition-all duration-300"
               :class="sidebarOpen ? 'w-64' : 'w-20'">
            <!-- Logo -->
            <div class="p-4 border-b border-gray-700">
                <a href="{{ route('dashboard') }}" class="flex items-center" :class="sidebarOpen ? 'space-x-3' : 'justify-center'">
                    <img src="{{ asset('images/logo_asesco.png') }}" alt="ASESCO BPO" class="w-12 h-12 object-contain">
                    <div x-show="sidebarOpen" x-cloak class="overflow-hidden">
                        <h1 class="text-white font-bold">ASESCO</h1>
                        <p class="text-gray-400 text-xs">BPO System</p>
                    </div>
                </a>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
                @if($isAdmin || ($user && $user->hasPermission('dashboard.ver')))
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center px-3 py-3 rounded-lg {{ $currentRoute === 'dashboard' ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all group"
                   :class="sidebarOpen ? 'space-x-3' : 'justify-center'"
                   :title="!sidebarOpen ? 'Dashboard' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak>Dashboard</span>
                </a>
                @endif

                @if($isAdmin || ($user && $user->hasPermission('canales.ver')))
                <a href="{{ route('channels.index') }}" 
                   class="flex items-center px-3 py-3 rounded-lg {{ str_starts_with($currentRoute, 'channels.') ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all group"
                   :class="sidebarOpen ? 'space-x-3' : 'justify-center'"
                   :title="!sidebarOpen ? 'Canales' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak>Canales</span>
                </a>
                @endif

                @if($isAdmin || ($user && $user->hasPermission('chats.ver')))
                <a href="{{ route('chat.index') }}" 
                   class="flex items-center px-3 py-3 rounded-lg {{ str_starts_with($currentRoute, 'chat.') ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all group"
                   :class="sidebarOpen ? 'space-x-3' : 'justify-center'"
                   :title="!sidebarOpen ? 'Chat' : ''">
                    <div class="relative flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <livewire:chat.sidebar-badge />
                    </div>
                    <span x-show="sidebarOpen" x-cloak class="flex items-center gap-2">
                        Chat
                    </span>
                </a>
                @endif

                @if($isAdmin || ($user && $user->hasPermission('campanas.ver')))
                <a href="{{ route('campaigns.index') }}" 
                   class="flex items-center px-3 py-3 rounded-lg {{ str_starts_with($currentRoute, 'campaigns.') ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all group"
                   :class="sidebarOpen ? 'space-x-3' : 'justify-center'"
                   :title="!sidebarOpen ? 'Mensajería Masiva' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak>Mensajería Masiva</span>
                </a>
                @endif

                @if($isAdmin || ($user && $user->hasPermission('clientes.ver')))
                <a href="#" 
                   class="flex items-center px-3 py-3 rounded-lg text-gray-300 hover:bg-gray-700/50 hover:text-white transition-all group"
                   :class="sidebarOpen ? 'space-x-3' : 'justify-center'"
                   :title="!sidebarOpen ? 'Clientes' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak>Clientes</span>
                </a>
                @endif

                @if($isAdmin || ($user && $user->hasPermission('cobranzas.ver')))
                <a href="#" 
                   class="flex items-center px-3 py-3 rounded-lg text-gray-300 hover:bg-gray-700/50 hover:text-white transition-all group"
                   :class="sidebarOpen ? 'space-x-3' : 'justify-center'"
                   :title="!sidebarOpen ? 'Cobranzas' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak>Cobranzas</span>
                </a>
                @endif

                @if($isAdmin || ($user && $user->hasPermission('reportes.ver')))
                <div x-data="{ open: {{ str_starts_with($currentRoute, 'reports.') ? 'true' : 'false' }} }">
                    <!-- Expanded mode -->
                    <template x-if="sidebarOpen">
                        <div>
                            <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-3 rounded-lg {{ str_starts_with($currentRoute, 'reports.') ? 'bg-gray-700/50 text-white' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all">
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    <span>Reportes</span>
                                </div>
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-collapse class="mt-1 ml-4 space-y-1">
                                <a href="{{ route('reports.chats-attended') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg {{ $currentRoute === 'reports.chats-attended' ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-400 hover:bg-gray-700/50 hover:text-white' }} transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    <span>Chats Atendidos</span>
                                </a>
                            </div>
                        </div>
                    </template>

                    <!-- Collapsed mode - popover -->
                    <template x-if="!sidebarOpen">
                        <div class="relative" x-data="{ showPopover: false }">
                            <button @click="showPopover = !showPopover" 
                                    @click.away="showPopover = false"
                                    class="w-full flex items-center justify-center px-3 py-3 rounded-lg {{ str_starts_with($currentRoute, 'reports.') ? 'bg-gray-700/50 text-white' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all"
                                    title="Reportes">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </button>
                            <div x-show="showPopover" x-cloak
                                 class="absolute left-full top-0 ml-2 w-48 bg-gray-800 rounded-lg shadow-xl py-2 z-50">
                                <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase">Reportes</div>
                                <a href="{{ route('reports.chats-attended') }}" class="flex items-center space-x-2 px-3 py-2 text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    <span>Chats Atendidos</span>
                                </a>
                            </div>
                        </div>
                    </template>
                </div>
                @endif

                <!-- Configuración con submenú -->
                @php
                    $canViewUsers = $isAdmin || ($user && $user->hasPermission('usuarios.ver'));
                    $canViewRoles = $isAdmin || ($user && $user->hasPermission('roles.ver'));
                    $canViewLabels = $isAdmin || ($user && $user->hasPermission('etiquetas.ver'));
                    $showSettings = $canViewUsers || $canViewRoles || $canViewLabels;
                @endphp
                
                @if($showSettings)
                <div x-data="{ open: {{ str_starts_with($currentRoute, 'settings.') ? 'true' : 'false' }} }">
                    <!-- Expanded mode -->
                    <template x-if="sidebarOpen">
                        <div>
                            <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-3 rounded-lg {{ str_starts_with($currentRoute, 'settings.') ? 'bg-gray-700/50 text-white' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all">
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>Configuración</span>
                                </div>
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-collapse class="mt-1 ml-4 space-y-1">
                                @if($canViewUsers)
                                <a href="{{ route('settings.users.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg {{ str_starts_with($currentRoute, 'settings.users') ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-400 hover:bg-gray-700/50 hover:text-white' }} transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    <span>Usuarios</span>
                                </a>
                                @endif
                                @if($canViewRoles)
                                <a href="{{ route('settings.roles.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg {{ str_starts_with($currentRoute, 'settings.roles') ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-400 hover:bg-gray-700/50 hover:text-white' }} transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    <span>Roles</span>
                                </a>
                                @endif
                                @if($canViewLabels)
                                <a href="{{ route('settings.labels.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg {{ str_starts_with($currentRoute, 'settings.labels') ? 'bg-gradient-to-r from-primary-500/20 to-secondary-500/20 text-white border-l-4 border-primary-500' : 'text-gray-400 hover:bg-gray-700/50 hover:text-white' }} transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <span>Etiquetas</span>
                                </a>
                                @endif
                            </div>
                        </div>
                    </template>

                    <!-- Collapsed mode - popover -->
                    <template x-if="!sidebarOpen">
                        <div class="relative" x-data="{ showPopover: false }">
                            <button @click="showPopover = !showPopover" 
                                    @click.away="showPopover = false"
                                    class="w-full flex items-center justify-center px-3 py-3 rounded-lg {{ str_starts_with($currentRoute, 'settings.') ? 'bg-gray-700/50 text-white' : 'text-gray-300 hover:bg-gray-700/50 hover:text-white' }} transition-all"
                                    title="Configuración">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </button>
                            <div x-show="showPopover" x-cloak
                                 class="absolute left-full top-0 ml-2 w-48 bg-gray-800 rounded-lg shadow-xl py-2 z-50">
                                <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase">Configuración</div>
                                @if($canViewUsers)
                                <a href="{{ route('settings.users.index') }}" class="flex items-center space-x-2 px-3 py-2 text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    <span>Usuarios</span>
                                </a>
                                @endif
                                @if($canViewRoles)
                                <a href="{{ route('settings.roles.index') }}" class="flex items-center space-x-2 px-3 py-2 text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    <span>Roles</span>
                                </a>
                                @endif
                                @if($canViewLabels)
                                <a href="{{ route('settings.labels.index') }}" class="flex items-center space-x-2 px-3 py-2 text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <span>Etiquetas</span>
                                </a>
                                @endif
                            </div>
                        </div>
                    </template>
                </div>
                @endif
            </nav>

            <!-- User -->
            <div class="p-3 border-t border-gray-700">
                <div class="flex items-center" :class="sidebarOpen ? 'space-x-3' : 'justify-center'">
                    <img class="w-10 h-10 rounded-full object-cover flex-shrink-0" src="{{ auth()->user()->profile_photo_url }}" alt="{{ auth()->user()->name }}" :title="!sidebarOpen ? '{{ auth()->user()->name }}' : ''">
                    <div x-show="sidebarOpen" x-cloak class="flex-1 min-w-0 overflow-hidden">
                        <p class="text-white text-sm font-medium truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <p class="text-gray-400 text-xs truncate">{{ auth()->user()->email ?? '' }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm sticky top-0 z-30">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <!-- Hamburger Button -->
                        <button @click="toggleSidebar()" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        
                        <!-- Breadcrumb -->
                        <div>
                            <div class="flex items-center space-x-2 text-sm">
                                @if($section)
                                    <span class="text-gray-400">{{ $section }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                @endif
                                @if($parentRoute)
                                    <a href="{{ route($parentRoute) }}" class="text-gray-400 hover:text-primary-500 transition-colors">
                                        {{ $breadcrumbs[$parentRoute]['title'] ?? '' }}
                                    </a>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                @endif
                                <span class="text-gray-800 font-medium">{{ $pageTitle }}</span>
                            </div>
                            <h2 class="text-xl font-semibold text-gray-800">{{ $pageTitle }}</h2>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Chat Notifications Badge -->
                        @if($isAdmin || ($user && $user->hasPermission('chats.ver')))
                            <livewire:chat.notification-badge />
                        @else
                            <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </button>
                        @endif
                        <livewire:auth.logout />
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    
    <!-- Browser Push Notifications -->
    <script>
        // Request notification permission on page load
        document.addEventListener('DOMContentLoaded', function() {
            if ('Notification' in window) {
                if (Notification.permission === 'default') {
                    // Show a subtle prompt to enable notifications
                    setTimeout(() => {
                        Notification.requestPermission().then(permission => {
                            if (permission === 'granted') {
                                console.log('Notificaciones habilitadas');
                            }
                        });
                    }, 3000);
                }
            }
        });

        // Function to show browser notification
        function showBrowserNotification(title, body, channelId, contactId) {
            if (!('Notification' in window)) {
                console.log('Este navegador no soporta notificaciones');
                return;
            }

            if (Notification.permission === 'granted') {
                const notification = new Notification(title, {
                    body: body,
                    icon: '{{ asset("images/logo_asesco.png") }}',
                    badge: '{{ asset("images/logo_asesco.png") }}',
                    tag: `chat-${channelId}-${contactId}`,
                    renotify: true,
                    requireInteraction: false,
                    silent: false
                });

                notification.onclick = function(event) {
                    event.preventDefault();
                    window.focus();
                    // Navigate to the chat
                    window.location.href = `{{ route('chat.index') }}?selectedChannelId=${channelId}&selectedContactId=${contactId}`;
                    notification.close();
                };

                // Auto close after 5 seconds
                setTimeout(() => notification.close(), 5000);
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        showBrowserNotification(title, body, channelId, contactId);
                    }
                });
            }
        }

        // Listen for new message events from Livewire
        window.addEventListener('browser-notification', event => {
            const { title, body, channelId, contactId } = event.detail;
            showBrowserNotification(title, body, channelId, contactId);
        });
    </script>
    
    <!-- Toast Notifications -->
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });

        function showToast(type, message) {
            Toast.fire({
                icon: type || 'success',
                title: message
            });
        }

        window.addEventListener('toast', event => {
            showToast(event.detail.type, event.detail.message);
        });

        window.addEventListener('confirm-delete', event => {
            Swal.fire({
                title: '¿Estás seguro?',
                text: event.detail.message || 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    popup: 'rounded-xl',
                    confirmButton: 'rounded-lg px-4 py-2',
                    cancelButton: 'rounded-lg px-4 py-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch('deleteConfirmed', { id: event.detail.id });
                }
            });
        });

        @if(session('toast'))
            document.addEventListener('DOMContentLoaded', () => {
                showToast('{{ session('toast.type') }}', '{{ session('toast.message') }}');
            });
        @endif
    </script>
</body>
</html>
