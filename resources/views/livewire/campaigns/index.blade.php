<div class="p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mensajería Masiva</h1>
            <p class="text-gray-600 mt-1">Gestiona tus campañas de mensajes masivos</p>
        </div>
        
        @if($canCreate)
        <div class="flex items-center gap-2">
            <a href="{{ route('campaigns.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-orange-500 to-pink-500 text-white rounded-lg hover:from-orange-600 hover:to-pink-600 transition-all shadow-md">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nueva Campaña
            </a>
            <button wire:click="openExcelModal"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all shadow-md">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Campaña Excel
            </button>
        </div>
        @endif
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            {{-- Búsqueda --}}
            <div class="flex-1">
                <div class="relative flex items-center">
                    <div class="absolute left-3 flex items-center justify-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search"
                           placeholder="Buscar campañas..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            {{-- Filtro de estado --}}
            <div class="w-full sm:w-44">
                <select wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Todos los estados</option>
                    <option value="draft">Borrador</option>
                    <option value="pending">Pendiente</option>
                    <option value="running">En progreso</option>
                    <option value="paused">Pausada</option>
                    <option value="completed">Completada</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>

            {{-- Items por página --}}
            <div class="w-full sm:w-20">
                <select wire:model.live="perPage"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th wire:click="sortBy('name')" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            <div class="flex items-center gap-1">
                                Nombre
                                @if($sortField === 'name')
                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Canal</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Enviado por</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Progreso</th>
                        <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            <div class="flex items-center gap-1">
                                Fecha
                                @if($sortField === 'created_at')
                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($this->campaigns as $campaign)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <a href="{{ route('campaigns.results', $campaign) }}" class="font-medium text-gray-900 hover:text-orange-600">
                                {{ $campaign->name }}
                            </a>
                            <p class="text-sm text-gray-500">{{ $campaign->total_recipients }} destinatarios</p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700">{{ $campaign->channel->name ?? 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700">{{ $campaign->user->name ?? 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($campaign->status === 'completed') bg-green-100 text-green-800
                                @elseif($campaign->status === 'running') bg-blue-100 text-blue-800
                                @elseif($campaign->status === 'paused') bg-yellow-100 text-yellow-800
                                @elseif($campaign->status === 'cancelled') bg-red-100 text-red-800
                                @elseif($campaign->status === 'pending') bg-orange-100 text-orange-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ $campaign->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 bg-gray-200 rounded-full h-2 w-24">
                                    <div class="h-2 rounded-full transition-all duration-300
                                        @if($campaign->status === 'completed') bg-green-500
                                        @elseif($campaign->status === 'running') bg-blue-500
                                        @else bg-gray-400
                                        @endif"
                                        style="width: {{ $campaign->progress_percentage }}%">
                                    </div>
                                </div>
                                <span class="text-sm text-gray-600">{{ $campaign->progress_percentage }}%</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $campaign->sent_count }} enviados, {{ $campaign->failed_count }} fallidos
                            </p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $campaign->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('campaigns.results', $campaign) }}" 
                                   class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                   title="Ver resultados">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>

                                @if($campaign->status === 'running')
                                <button wire:click="pauseCampaign({{ $campaign->id }})"
                                        wire:confirm="¿Pausar esta campaña?"
                                        class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors"
                                        title="Pausar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                                @endif

                                @if($campaign->status === 'paused')
                                <button wire:click="resumeCampaign({{ $campaign->id }})"
                                        wire:confirm="¿Reanudar esta campaña?"
                                        class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                        title="Reanudar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                                @endif

                                @if(in_array($campaign->status, ['pending', 'running', 'paused']))
                                <button wire:click="cancelCampaign({{ $campaign->id }})"
                                        wire:confirm="¿Cancelar esta campaña? Esta acción no se puede deshacer."
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Cancelar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                                @endif

                                @if($canDelete && !in_array($campaign->status, ['running']))
                                <button wire:click="deleteCampaign({{ $campaign->id }})"
                                        wire:confirm="¿Eliminar esta campaña? Esta acción no se puede deshacer."
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Eliminar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay campañas</h3>
                            <p class="mt-1 text-sm text-gray-500">Crea tu primera campaña de mensajería masiva.</p>
                            @if($canCreate)
                            <div class="mt-6">
                                <a href="{{ route('campaigns.create') }}" 
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-orange-500 to-pink-500 text-white rounded-lg hover:from-orange-600 hover:to-pink-600 transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Nueva Campaña
                                </a>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if($this->campaigns->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $this->campaigns->links() }}
        </div>
        @endif
    </div>

    {{-- Modal Campaña Excel --}}
    @if($showExcelModal)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-trap.noscroll="true">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" wire:click="closeExcelModal"></div>
        
        {{-- Modal --}}
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Nueva Campaña desde Excel</h2>
                    <p class="text-sm text-gray-500 mt-1">Sube un archivo Excel con los destinatarios</p>
                </div>
                <button wire:click="closeExcelModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-5">
                {{-- Descargar plantilla --}}
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div>
                                <p class="font-medium text-green-800">Plantilla Excel</p>
                                <p class="text-sm text-green-600">Descarga la plantilla con las columnas requeridas</p>
                            </div>
                        </div>
                        <button wire:click="downloadExcelTemplate"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Descargar
                        </button>
                    </div>
                </div>

                {{-- Nombre de campaña --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de la campaña *</label>
                    <input type="text" 
                           wire:model="excelCampaignName"
                           placeholder="Ej: Recordatorio de pago - Abril 2026"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('excelCampaignName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Canal --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Canal de WhatsApp *</label>
                    <select wire:model="excelChannelId"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option value="">Seleccionar canal...</option>
                        @foreach($this->availableChannels as $channel)
                        <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                        @endforeach
                    </select>
                    @error('excelChannelId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Mensaje --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mensaje *</label>
                    <textarea wire:model="excelMessage"
                              rows="4"
                              placeholder="Escribe tu mensaje aquí..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"></textarea>
                    @error('excelMessage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="mt-2 p-3 bg-blue-50 rounded-lg">
                        <p class="text-xs text-blue-700">
                            Variables: <code class="bg-blue-100 px-1 rounded">{nombre}</code> 
                            <code class="bg-blue-100 px-1 rounded">{val1}</code> 
                            <code class="bg-blue-100 px-1 rounded">{val2}</code>
                        </p>
                    </div>
                </div>

                {{-- Subir archivo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Archivo Excel *</label>
                    <div x-data="{ isDragging: false }"
                         class="border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                         :class="isDragging ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-gray-400'"
                         x-on:dragover.prevent="isDragging = true"
                         x-on:dragleave.prevent="isDragging = false"
                         x-on:drop.prevent="
                             isDragging = false;
                             const file = $event.dataTransfer.files[0];
                             if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
                                 $refs.excelInput.files = $event.dataTransfer.files;
                                 $refs.excelInput.dispatchEvent(new Event('change', { bubbles: true }));
                             }
                         ">
                        <input type="file" 
                               wire:model="excelFile"
                               accept=".xlsx,.xls"
                               class="hidden"
                               id="excelUpload"
                               x-ref="excelInput">
                        <label for="excelUpload" class="cursor-pointer">
                            <svg class="mx-auto h-10 w-10" :class="isDragging ? 'text-green-500' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mt-2 text-sm" :class="isDragging ? 'text-green-600' : 'text-gray-600'">
                                <span x-show="!isDragging">Arrastra un archivo Excel o haz clic para seleccionar</span>
                                <span x-show="isDragging">Suelta el archivo aquí</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">Formatos: .xlsx, .xls (máx. 5MB)</p>
                        </label>
                    </div>
                    @error('excelFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    {{-- Loading --}}
                    <div wire:loading wire:target="excelFile" class="mt-2 flex items-center gap-2 text-sm text-orange-600">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Procesando archivo...
                    </div>
                </div>

                {{-- Preview --}}
                @if($excelTotalRows > 0)
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700">
                            Vista previa ({{ $excelTotalRows }} destinatarios)
                        </label>
                    </div>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Teléfono</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Nombre</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Val1</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Val2</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($excelPreview as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-mono">{{ $row['phone_number'] }}</td>
                                    <td class="px-4 py-2">{{ $row['name'] ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $row['val1'] ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $row['val2'] ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($excelTotalRows > 5)
                        <p class="px-4 py-2 text-xs text-gray-500 bg-gray-50">
                            Mostrando 5 de {{ $excelTotalRows }} destinatarios
                        </p>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-200">
                <button wire:click="closeExcelModal"
                        class="px-4 py-2 text-gray-700 hover:text-gray-900 transition-colors">
                    Cancelar
                </button>
                <button wire:click="processExcelCampaign"
                        wire:loading.attr="disabled"
                        @if($excelTotalRows === 0) disabled @endif
                        class="px-6 py-2 bg-gradient-to-r from-orange-500 to-pink-500 text-white rounded-lg hover:from-orange-600 hover:to-pink-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="processExcelCampaign">
                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Procesar e Iniciar
                    </span>
                    <span wire:loading wire:target="processExcelCampaign" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Procesando...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
