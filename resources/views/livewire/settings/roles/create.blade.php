<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('settings.roles.index') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a roles
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Crear Rol</h1>
        <p class="text-gray-500 text-sm">Define un nuevo rol con sus permisos</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- Info Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Información del Rol</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Identificador</label>
                    <input wire:model="name" type="text" id="name" 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all @error('name') border-red-500 @enderror"
                           placeholder="ej: supervisor">
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">Nombre visible</label>
                    <input wire:model="display_name" type="text" id="display_name" 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all @error('display_name') border-red-500 @enderror"
                           placeholder="ej: Supervisor">
                    @error('display_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                    <input wire:model="description" type="text" id="description" 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"
                           placeholder="Descripción del rol">
                </div>

                <div>
                    <label for="color" class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                    <div class="flex items-center gap-3">
                        <input wire:model="color" type="color" id="color" 
                               class="w-12 h-12 rounded-lg border border-gray-300 cursor-pointer">
                        <input wire:model="color" type="text" 
                               class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"
                               placeholder="#6b7280">
                    </div>
                </div>
            </div>
        </div>

        <!-- Permissions Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Permisos por Módulo</h2>
            <p class="text-gray-500 text-sm mb-6">Selecciona los permisos que tendrá este rol</p>

            <div class="space-y-4">
                @foreach($modules as $module)
                    @php
                        $modulePermissionIds = $module->permissions->pluck('id')->toArray();
                        $selectedCount = count(array_intersect($modulePermissionIds, $selectedPermissions));
                        $allSelected = count($modulePermissionIds) > 0 && $selectedCount === count($modulePermissionIds);
                    @endphp
                    <div class="border border-gray-200 rounded-xl overflow-hidden" wire:key="module-{{ $module->id }}">
                        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="toggleModule({{ $module->id }})"
                                        class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all
                                        {{ $allSelected ? 'bg-primary-500 border-primary-500' : ($selectedCount > 0 ? 'bg-primary-200 border-primary-300' : 'border-gray-300') }}">
                                    @if($allSelected)
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @elseif($selectedCount > 0)
                                        <div class="w-2 h-2 bg-primary-500 rounded-sm"></div>
                                    @endif
                                </button>
                                <span class="font-medium text-gray-800">{{ $module->display_name }}</span>
                            </div>
                            <span class="text-xs text-gray-500">{{ $selectedCount }}/{{ count($modulePermissionIds) }}</span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach($module->permissions as $permission)
                                <label class="flex items-center gap-2 cursor-pointer group" wire:key="permission-{{ $permission->id }}">
                                    <input type="checkbox" wire:model.live="selectedPermissions" value="{{ $permission->id }}"
                                           class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500">
                                    <span class="text-sm text-gray-600 group-hover:text-gray-900">{{ ucfirst($permission->action) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('settings.roles.index') }}" 
               class="px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-all">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 gradient-bg text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-2"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75 cursor-not-allowed">
                <svg wire:loading class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove>Crear Rol</span>
                <span wire:loading>Guardando...</span>
            </button>
        </div>
    </form>
</div>
