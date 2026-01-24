<div class="max-w-2xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('settings.users.index') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a usuarios
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Crear Usuario</h1>
        <p class="text-gray-500 text-sm">Completa los datos para crear un nuevo usuario</p>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form wire:submit="save" class="space-y-5">
            <!-- Photo Upload -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Foto de perfil</label>
                <div class="flex items-center gap-4">
                    <div class="shrink-0">
                        @if ($photo)
                            <img class="h-16 w-16 object-cover rounded-full" src="{{ $photo->temporaryUrl() }}" alt="Preview">
                        @else
                            <div class="h-16 w-16 rounded-full gradient-bg flex items-center justify-center text-white text-xl font-semibold">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                    <label class="cursor-pointer">
                        <span class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Seleccionar imagen
                        </span>
                        <input type="file" wire:model="photo" accept="image/*" class="sr-only">
                    </label>
                    <div wire:loading wire:target="photo" class="text-sm text-gray-500">
                        <svg class="animate-spin h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
                @error('photo')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">JPG, PNG o GIF. Máximo 2MB.</p>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                <input wire:model="name" type="text" id="name" 
                       class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all @error('name') border-red-500 @enderror"
                       placeholder="Nombre del usuario">
                @error('name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                <input wire:model="email" type="email" id="email" 
                       class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all @error('email') border-red-500 @enderror"
                       placeholder="correo@ejemplo.com">
                @error('email')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                <input wire:model="password" type="password" id="password" 
                       class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all @error('password') border-red-500 @enderror"
                       placeholder="Mínimo 8 caracteres">
                @error('password')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                <input wire:model="password_confirmation" type="password" id="password_confirmation" 
                       class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"
                       placeholder="Repite la contraseña">
            </div>

            <!-- Roles -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($roles as $role)
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-all
                            {{ in_array($role->id, $selectedRoles) ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="checkbox" wire:model.live="selectedRoles" value="{{ $role->id }}" class="sr-only">
                            <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $role->color }}"></span>
                            <span class="text-sm {{ in_array($role->id, $selectedRoles) ? 'text-primary-700 font-medium' : 'text-gray-600' }}">
                                {{ $role->display_name }}
                            </span>
                            @if(in_array($role->id, $selectedRoles))
                                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </label>
                    @endforeach
                </div>
                @if(count($roles) == 0)
                    <p class="text-sm text-gray-500">No hay roles disponibles</p>
                @endif
            </div>

            <!-- Status Toggle -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estatus</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="isActive" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                    <span class="ms-3 text-sm font-medium {{ $isActive ? 'text-green-600' : 'text-gray-500' }}">
                        {{ $isActive ? 'Activo' : 'Inactivo' }}
                    </span>
                </label>
                <p class="mt-1 text-xs text-gray-500">Los usuarios inactivos no pueden iniciar sesión</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('settings.users.index') }}" 
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
                    <span wire:loading.remove>Crear Usuario</span>
                    <span wire:loading>Guardando...</span>
                </button>
            </div>
        </form>
    </div>
</div>

