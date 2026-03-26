<div class="space-y-6">
    <!-- Header With Quick Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Reporte de Promesas de Pago</h1>
                <p class="text-gray-500 text-sm mt-1">Control detallado de promesas y programación de mensajes automáticos</p>
            </div>
            
            <div class="flex flex-wrap gap-2">
                <button wire:click="resetFilters" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Limpiar Filtros
                </button>
            </div>
        </div>

        <!-- Filters Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mt-6">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Buscar por Fechas de</label>
                <select wire:model.live="dateFilterType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="created_at">Fecha de Creación de Promesa</option>
                    <option value="promised_date">Fecha Programada de Envío</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            
            @if(auth()->user()->hasRole('admin'))
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Agente</label>
                <select wire:model.live="userId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Todos los agentes</option>
                    @foreach($this->users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Estado del Mensaje</label>
                <select wire:model.live="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="all">Todos los estados</option>
                    <option value="pending">Pendiente de Envío</option>
                    <option value="sent">Mensaje Enviado</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 font-medium">Fecha Creación</th>
                        <th class="px-6 py-4 font-medium">Fecha de Envío</th>
                        <th class="px-6 py-4 font-medium">Teléfono / Cliente</th>
                        <th class="px-6 py-4 font-medium">Canal</th>
                        @if(auth()->user()->hasRole('admin'))
                        <th class="px-6 py-4 font-medium">Agente</th>
                        @endif
                        <th class="px-6 py-4 font-medium">Monto Promesa</th>
                        <th class="px-6 py-4 font-medium">Estado</th>
                        <th class="px-6 py-4 font-medium text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($this->promises as $promise)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                {{ \Carbon\Carbon::parse($promise->created_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-blue-700 bg-blue-50 px-2 py-1 rounded">
                                    {{ \Carbon\Carbon::parse($promise->promised_date)->format('d/m/Y H:i') }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $promise->contact->phone_number }}</div>
                                <div class="text-xs text-gray-500">{{ $promise->contact->name ?? $promise->contact->push_name ?? 'Sin Nombre' }}</div>
                            </td>
                            <td class="px-6 py-4 truncate max-w-[120px]">
                                {{ $promise->contact->channel->name ?? 'N/A' }}
                            </td>
                            @if(auth()->user()->hasRole('admin'))
                            <td class="px-6 py-4">
                                <div class="text-gray-800 font-medium">{{ $promise->user->name }}</div>
                            </td>
                            @endif
                            <td class="px-6 py-4 font-bold text-gray-800">
                                ${{ number_format($promise->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($promise->message_sent)
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Enviado
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Pendiente
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button wire:click="openModal({{ $promise->id }})" class="text-orange-500 hover:text-orange-700 p-2 rounded-lg hover:bg-orange-50 transition-colors tooltip" title="Ver Detalle">
                                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasRole('admin') ? '8' : '7' }}" class="px-6 py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                <p class="text-base text-gray-600 font-medium">No se encontraron promesas de pago</p>
                                <p class="text-sm mt-1">Ajusta los filtros de búsqueda para ver más resultados.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->promises->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $this->promises->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Ver Detalle -->
    @if($showModal && $selectedPromise)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" aria-hidden="true" wire:click="closeModal"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-gray-100">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-5">
                        <h3 class="text-xl leading-6 font-bold text-gray-900" id="modal-title">
                            Detalles de la Promesa
                        </h3>
                        <button wire:click="closeModal" class="text-gray-400 hover:text-gray-500 bg-gray-100 hover:bg-gray-200 p-1.5 rounded-full transition-colors">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente (Teléfono)</p>
                                <p class="text-lg font-bold text-gray-900 mt-1">{{ $selectedPromise->contact->phone_number }}</p>
                            </div>
                            <div class="mt-3 sm:mt-0 sm:text-right">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Agente Creador</p>
                                <p class="text-sm font-semibold text-gray-800 mt-1">{{ $selectedPromise->user->name }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100">
                                <p class="text-xs font-medium text-blue-600">Fecha de Promesa</p>
                                <p class="text-sm font-bold text-gray-900 mt-1">{{ \Carbon\Carbon::parse($selectedPromise->promised_date)->format('d/m/Y h:i A') }}</p>
                            </div>
                            <div class="bg-orange-50/50 p-3 rounded-xl border border-orange-100">
                                <p class="text-xs font-medium text-orange-600">Monto</p>
                                <p class="text-sm font-bold text-gray-900 mt-1">${{ number_format($selectedPromise->amount, 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                Mensaje Automático Configurado
                            </h4>
                            <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                                @if($selectedPromise->notes)
                                    <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $selectedPromise->notes }}</p>
                                @else
                                    <p class="text-sm text-gray-500 italic">No se programó ningún texto para esta promesa.</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                            <p class="text-sm font-medium text-gray-500">Estado actual de la promesa:</p>
                            @if($selectedPromise->message_sent)
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800">Mensaje Enviado Correctamente</span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800">Programado en Espera</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse rounded-b-2xl border-t border-gray-100">
                    <button type="button" wire:click="closeModal" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-gray-800 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Cerrar Detalle
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
