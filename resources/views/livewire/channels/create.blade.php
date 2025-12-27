<div class="max-w-2xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('channels.index') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a canales
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Crear Canal</h1>
        <p class="text-gray-500 text-sm">Configura un nuevo canal de WhatsApp</p>
    </div>

    <!-- Info Card -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
        <div class="flex gap-3">
            <svg class="w-6 h-6 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-700">
                <p class="font-medium mb-1">¿Cómo funciona?</p>
                <ol class="list-decimal list-inside space-y-1 text-blue-600">
                    <li>Crea el canal con un nombre identificador</li>
                    <li>Haz clic en "Conectar" para generar el código QR</li>
                    <li>Escanea el QR con WhatsApp desde tu teléfono</li>
                    <li>El número se vinculará automáticamente</li>
                </ol>
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
                       placeholder="Ej: Ventas Principal, Soporte, Cobranzas">
                @error('name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="instance_name" class="block text-sm font-medium text-gray-700 mb-1">Identificador único</label>
                <input wire:model="instance_name" type="text" id="instance_name" 
                       class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all @error('instance_name') border-red-500 @enderror"
                       placeholder="Ej: ventas_principal">
                <p class="mt-1 text-xs text-gray-400">Solo letras, números, guiones y guiones bajos. No se puede cambiar después.</p>
                @error('instance_name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Users Assignment -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Usuarios asignados (opcional)</label>
                <p class="text-xs text-gray-400 mb-3">Selecciona los usuarios que podrán atender este canal</p>
                <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-xl p-3 space-y-2">
                    @forelse($users as $user)
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
                    @empty
                        <p class="text-sm text-gray-400 text-center py-2">No hay usuarios disponibles</p>
                    @endforelse
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
                    <span wire:loading.remove>Crear Canal</span>
                    <span wire:loading>Creando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
