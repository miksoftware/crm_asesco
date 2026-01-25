<div class="p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mensajería Masiva</h1>
            <p class="text-gray-600 mt-1">Gestiona tus campañas de mensajes masivos</p>
        </div>
        
        @if($canCreate)
        <a href="{{ route('campaigns.create') }}" 
           class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-orange-500 to-pink-500 text-white rounded-lg hover:from-orange-600 hover:to-pink-600 transition-all shadow-md">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nueva Campaña
        </a>
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
</div>
