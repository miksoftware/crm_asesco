<div class="max-w-2xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('channels.index') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a canales
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Editar Canal</h1>
        <p class="text-gray-500 text-sm">Modifica la configuración del canal</p>
    </div>

    <!-- Status Card -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Estado actual</p>
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium"
                          style="color: {{ $channel->status_color }}">
                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $channel->status_color }}"></span>
                        {{ $channel->status_label }}
                    </span>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Instancia</p>
                <p class="text-sm font-mono text-gray-600">{{ $channel->instance_name }}</p>
                @if($channel->phone_number)
                    <p class="text-xs text-gray-400 mt-1">Tel: {{ $channel->phone_number }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form wire:submit="save" class="space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre del canal</label>
                <input wire:model="name" type="text" id="name" 
                       class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all @error('name') border-red-500 @enderror"
                       placeholder="Ej: Ventas Principal">
                @error('name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                </label>
                <span class="text-sm font-medium text-gray-700">Canal activo</span>
            </div>

            <!-- Users Assignment -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Usuarios asignados</label>
                <p class="text-xs text-gray-400 mb-3">Selecciona los usuarios que podrán atender este canal</p>
                <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-xl p-3 space-y-2">
                    @foreach($users as $user)
                        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" wire:model="selectedUsers" value="{{ $user->id }}"
                                   class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full gradient-bg flex items-center justify-center text-white text-sm font-semibold">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $user->email }}</p>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('channels.index') }}" 
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
                    <span wire:loading.remove>Guardar Cambios</span>
                    <span wire:loading>Guardando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
