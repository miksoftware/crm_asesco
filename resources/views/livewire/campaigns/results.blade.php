<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a campañas
        </a>
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $campaign->name }}</h1>
                <p class="text-gray-600 mt-1">
                    Canal: {{ $campaign->channel->name ?? 'N/A' }} • 
                    Creada: {{ $campaign->created_at->format('d/m/Y H:i') }}
                </p>
            </div>
            
            <div class="flex items-center gap-2">
                @if(in_array($campaign->status, ['draft', 'paused']))
                <button wire:click="startCampaign"
                        wire:confirm="¿Iniciar esta campaña?"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $campaign->status === 'paused' ? 'Reanudar' : 'Iniciar' }}
                </button>
                @endif

                @if($campaign->status === 'running')
                <button wire:click="pauseCampaign"
                        wire:confirm="¿Pausar esta campaña?"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Pausar
                </button>
                @endif

                @if(in_array($campaign->status, ['pending', 'running', 'paused']))
                <button wire:click="cancelCampaign"
                        wire:confirm="¿Cancelar esta campaña? Esta acción no se puede deshacer."
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Cancelar
                </button>
                @endif

                @if(in_array($campaign->status, ['completed', 'paused', 'cancelled']) && $campaign->failed_count > 0)
                <button wire:click="retryFailed"
                        wire:confirm="¿Reintentar enviar los {{ $campaign->failed_count }} mensajes fallidos?"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reintentar fallidos
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ $this->stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Enviados</p>
            <p class="text-2xl font-bold text-green-600">{{ $this->stats['sent'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Fallidos</p>
            <p class="text-2xl font-bold text-red-600">{{ $this->stats['failed'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Pendientes</p>
            <p class="text-2xl font-bold text-gray-600">{{ $this->stats['pending'] }}</p>
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700">Progreso</span>
            <div class="flex items-center gap-2">
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
                <span class="text-sm text-gray-600">{{ $this->stats['progress'] }}%</span>
            </div>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3">
            <div class="h-3 rounded-full transition-all duration-500
                @if($campaign->status === 'completed') bg-green-500
                @elseif($campaign->status === 'running') bg-blue-500
                @else bg-gray-400
                @endif"
                style="width: {{ $this->stats['progress'] }}%">
            </div>
        </div>
        @if($campaign->started_at)
        <p class="text-xs text-gray-500 mt-2">
            Iniciada: {{ $campaign->started_at->format('d/m/Y H:i') }}
            @if($campaign->completed_at)
            • Completada: {{ $campaign->completed_at->format('d/m/Y H:i') }}
            @endif
        </p>
        @endif
    </div>

    {{-- Message preview --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <h3 class="text-sm font-medium text-gray-700 mb-2">Mensaje de la campaña</h3>
        <div class="p-3 bg-gray-100 rounded-lg">
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $campaign->message_content }}</p>
        </div>
    </div>

    {{-- Recipients table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row gap-4">
                {{-- Search --}}
                <div class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               placeholder="Buscar por teléfono o nombre..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                </div>

                {{-- Status filter --}}
                <div class="w-full sm:w-40">
                    <select wire:model.live="statusFilter"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option value="">Todos</option>
                        <option value="pending">Pendientes</option>
                        <option value="sent">Enviados</option>
                        <option value="failed">Fallidos</option>
                        <option value="invalid">Inválidos</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Teléfono</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Enviado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($this->recipients as $recipient)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-mono text-sm">{{ $recipient->phone_number }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $recipient->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($recipient->status === 'sent') bg-green-100 text-green-800
                                @elseif($recipient->status === 'failed') bg-red-100 text-red-800
                                @elseif($recipient->status === 'invalid') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ $recipient->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $recipient->sent_at ? $recipient->sent_at->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-red-600">
                            {{ $recipient->error_message ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No se encontraron destinatarios
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($this->recipients->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $this->recipients->links() }}
        </div>
        @endif
    </div>
</div>
