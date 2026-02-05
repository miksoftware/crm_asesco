<div class="space-y-6" x-data="{ ready: false }" x-init="setTimeout(() => { ready = true; initReportCharts(); }, 100)">
    <!-- Header with Quick Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Reporte de Chats Atendidos</h1>
                <p class="text-gray-500 text-sm mt-1">Análisis detallado de la actividad de mensajería por agente</p>
            </div>
            
            <div class="flex flex-wrap gap-2">
                <!-- Export PDF Button -->
                <button onclick="exportToPDF()" class="px-4 py-1.5 text-xs font-medium rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Exportar PDF
                </button>
                
                <!-- Quick Date Buttons -->
                <button wire:click="setQuickDate('today')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $dateFrom === now()->format('Y-m-d') && $dateTo === now()->format('Y-m-d') ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    Hoy
                </button>
                <button wire:click="setQuickDate('yesterday')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                    Ayer
                </button>
                <button wire:click="setQuickDate('week')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                    Esta Semana
                </button>
                <button wire:click="setQuickDate('month')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                    Este Mes
                </button>
                <button wire:click="resetFilters" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition-colors">
                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reiniciar
                </button>
            </div>
        </div>

        <!-- Filters Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mt-6">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Agente</label>
                <select wire:model.live="userId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Todos los agentes</option>
                    @foreach($this->users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Canal</label>
                <select wire:model.live="channelId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Todos los canales</option>
                    @foreach($this->channels as $channel)
                        <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Dirección</label>
                <select wire:model.live="messageDirection" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="all">Todos</option>
                    <option value="incoming">Recibidos</option>
                    <option value="outgoing">Enviados</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Agrupar por</label>
                <select wire:model.live="groupBy" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="day">Día</option>
                    <option value="week">Semana</option>
                    <option value="month">Mes</option>
                </select>
            </div>
        </div>
    </div>

    <!-- KPI Cards con descripciones claras -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-800 mt-3">{{ number_format($this->totalMessages) }}</p>
            <p class="text-xs text-gray-500">Total Mensajes</p>
            <p class="text-[10px] text-gray-400 mt-1">Enviados + Recibidos</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-pink-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-800 mt-3">{{ number_format($this->totalConversations) }}</p>
            <p class="text-xs text-gray-500">Conversaciones</p>
            <p class="text-[10px] text-gray-400 mt-1">Contactos únicos</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-800 mt-3">{{ number_format($this->totalIncoming) }}</p>
            <p class="text-xs text-gray-500">Recibidos</p>
            <p class="text-[10px] text-gray-400 mt-1">Mensajes de clientes</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-800 mt-3">{{ number_format($this->totalOutgoing) }}</p>
            <p class="text-xs text-gray-500">Enviados</p>
            <p class="text-[10px] text-gray-400 mt-1">Mensajes de agentes</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-800 mt-3">{{ number_format($this->avgMessagesPerDay, 1) }}</p>
            <p class="text-xs text-gray-500">Promedio/Día</p>
            <p class="text-[10px] text-gray-400 mt-1">Mensajes diarios</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-cyan-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
            @php
                $rate = $this->totalIncoming > 0 ? round(($this->totalOutgoing / $this->totalIncoming) * 100, 1) : 0;
            @endphp
            <p class="text-2xl font-bold text-gray-800 mt-3">{{ $rate }}%</p>
            <p class="text-xs text-gray-500">Tasa Respuesta</p>
            <p class="text-[10px] text-gray-400 mt-1">Enviados / Recibidos</p>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Mensajes por {{ $groupBy === 'day' ? 'Día' : ($groupBy === 'week' ? 'Semana' : 'Mes') }}</h3>
                    <p class="text-xs text-gray-500">Volumen de mensajes en el período seleccionado</p>
                </div>
            </div>
            <div class="h-80" wire:ignore>
                <canvas id="messagesOverTimeChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Top Agentes</h3>
                <p class="text-xs text-gray-500">Ranking por mensajes enviados</p>
            </div>
            <div class="space-y-3 max-h-80 overflow-y-auto">
                @forelse($this->messagesByUser as $index => $user)
                    <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background-color: {{ $user['color'] }}">
                                {{ $index + 1 }}
                            </span>
                            <span class="text-sm font-medium text-gray-700">{{ $user['name'] }}</span>
                        </div>
                        <span class="text-sm font-bold text-gray-800">{{ number_format($user['total']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">Sin datos de agentes</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Actividad por Hora</h3>
                <p class="text-xs text-gray-500">Distribución horaria de mensajes</p>
            </div>
            <div class="h-64" wire:ignore>
                <canvas id="messagesByHourChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Actividad por Día</h3>
                <p class="text-xs text-gray-500">Distribución semanal de mensajes</p>
            </div>
            <div class="h-64" wire:ignore>
                <canvas id="messagesByDayChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Tipos de Mensaje</h3>
                <p class="text-xs text-gray-500">Texto, imagen, audio, etc.</p>
            </div>
            <div class="h-64" wire:ignore>
                <canvas id="messagesByTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 3 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Mensajes por Canal</h3>
                <p class="text-xs text-gray-500">Distribución por línea de WhatsApp</p>
            </div>
            <div class="h-64" wire:ignore>
                <canvas id="messagesByChannelChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Top 10 Contactos</h3>
                <p class="text-xs text-gray-500">Contactos con más interacción</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-2 font-medium text-gray-500">Contacto</th>
                            <th class="text-center py-2 px-2 font-medium text-gray-500">Recibidos</th>
                            <th class="text-center py-2 px-2 font-medium text-gray-500">Enviados</th>
                            <th class="text-right py-2 px-2 font-medium text-gray-500">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->topContacts as $contact)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-2">
                                    <div class="font-medium text-gray-800">{{ Str::limit($contact['name'], 20) }}</div>
                                    <div class="text-xs text-gray-400">{{ $contact['phone'] }}</div>
                                </td>
                                <td class="text-center py-2 px-2 text-green-600">{{ number_format($contact['incoming']) }}</td>
                                <td class="text-center py-2 px-2 text-blue-600">{{ number_format($contact['outgoing']) }}</td>
                                <td class="text-right py-2 px-2 font-bold text-gray-800">{{ number_format($contact['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">Sin datos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabla Detallada de Contactos -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" id="contacts-table-section">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Detalle de Contactos</h3>
                <p class="text-xs text-gray-500">Lista completa de contactos con actividad en el período</p>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500">Mostrar:</label>
                <select wire:model.live="perPage" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="text-left py-3 px-3 font-medium text-gray-600">ID</th>
                        <th class="text-left py-3 px-3 font-medium text-gray-600">Nombre</th>
                        <th class="text-left py-3 px-3 font-medium text-gray-600">WhatsApp</th>
                        <th class="text-left py-3 px-3 font-medium text-gray-600">Etiquetas</th>
                        <th class="text-left py-3 px-3 font-medium text-gray-600">Último Contacto</th>
                        <th class="text-left py-3 px-3 font-medium text-gray-600">Fecha Creación</th>
                        <th class="text-left py-3 px-3 font-medium text-gray-600">Agente Principal</th>
                        <th class="text-center py-3 px-3 font-medium text-gray-600">Enviados</th>
                        <th class="text-center py-3 px-3 font-medium text-gray-600">Recibidos</th>
                        <th class="text-center py-3 px-3 font-medium text-gray-600">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->contactsTable as $contact)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-3 text-gray-500">{{ $contact->id }}</td>
                            <td class="py-3 px-3">
                                <div class="font-medium text-gray-800">{{ $contact->name ?: $contact->push_name ?: '-' }}</div>
                            </td>
                            <td class="py-3 px-3 text-gray-600">{{ $contact->phone_number }}</td>
                            <td class="py-3 px-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($contact->labelRelations as $label)
                                        <span class="px-2 py-0.5 text-xs rounded-full text-white" style="background-color: {{ $label->color }}">
                                            {{ $label->name }}
                                        </span>
                                    @empty
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                @if($contact->last_message_at)
                                    <div class="text-gray-800">{{ \Carbon\Carbon::parse($contact->last_message_at)->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($contact->last_message_at)->format('H:i') }}</div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-gray-600">{{ $contact->created_at->format('d/m/Y') }}</td>
                            <td class="py-3 px-3">
                                @if($contact->assignedUser)
                                    <div class="text-gray-800">{{ $contact->assignedUser->name }}</div>
                                    <div class="text-xs text-gray-400">ID: {{ $contact->assignedUser->id }}</div>
                                @else
                                    <span class="text-gray-400">Sin asignar</span>
                                @endif
                            </td>
                            <td class="text-center py-3 px-3 text-blue-600 font-medium">{{ number_format($contact->sent_messages) }}</td>
                            <td class="text-center py-3 px-3 text-green-600 font-medium">{{ number_format($contact->received_messages) }}</td>
                            <td class="text-center py-3 px-3 font-bold text-gray-800">{{ number_format($contact->total_messages) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-gray-500">No hay contactos con actividad en el período seleccionado</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        @if($this->contactsTable->hasPages())
            <div class="mt-4 border-t border-gray-200 pt-4">
                {{ $this->contactsTable->links() }}
            </div>
        @endif
    </div>

    <!-- Loading Overlay -->
    <div wire:loading.flex class="fixed inset-0 bg-black/20 backdrop-blur-sm z-50 items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl p-6 flex items-center gap-3">
            <svg class="animate-spin h-6 w-6 text-orange-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-700 font-medium">Actualizando datos...</span>
        </div>
    </div>
</div>

@script
<script>
    window.reportChartInstances = window.reportChartInstances || {};
    
    window.initReportCharts = function() {
        const data = {
            messagesByDate: @json($this->messagesByDate),
            messagesByHour: @json($this->messagesByHour),
            messagesByDayOfWeek: @json($this->messagesByDayOfWeek),
            messagesByType: @json($this->messagesByType),
            messagesByChannel: @json($this->messagesByChannel)
        };
        window.renderAllCharts(data);
    };
    
    window.renderAllCharts = function(data) {
        window.renderMessagesOverTime(data.messagesByDate);
        window.renderMessagesByHour(data.messagesByHour);
        window.renderMessagesByDay(data.messagesByDayOfWeek);
        window.renderMessagesByType(data.messagesByType);
        window.renderMessagesByChannel(data.messagesByChannel);
    };
    
    window.destroyChart = function(id) {
        if (window.reportChartInstances[id]) {
            window.reportChartInstances[id].destroy();
            delete window.reportChartInstances[id];
        }
    };
    
    window.renderMessagesOverTime = function(data) {
        const ctx = document.getElementById('messagesOverTimeChart');
        if (!ctx || !data) return;
        window.destroyChart('messagesOverTime');
        window.reportChartInstances.messagesOverTime = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Mensajes',
                    data: data.data || [],
                    backgroundColor: 'rgba(249, 115, 22, 0.8)',
                    borderColor: 'rgb(249, 115, 22)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    };
    
    window.renderMessagesByHour = function(data) {
        const ctx = document.getElementById('messagesByHourChart');
        if (!ctx || !data) return;
        window.destroyChart('messagesByHour');
        window.reportChartInstances.messagesByHour = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Mensajes',
                    data: data.data || [],
                    borderColor: 'rgb(236, 72, 153)',
                    backgroundColor: 'rgba(236, 72, 153, 0.1)',
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    };
    
    window.renderMessagesByDay = function(data) {
        const ctx = document.getElementById('messagesByDayChart');
        if (!ctx || !data) return;
        window.destroyChart('messagesByDay');
        window.reportChartInstances.messagesByDay = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Mensajes',
                    data: data.data || [],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(6, 182, 212, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                    ],
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    };
    
    window.renderMessagesByType = function(data) {
        const ctx = document.getElementById('messagesByTypeChart');
        if (!ctx || !data || !data.length) return;
        window.destroyChart('messagesByType');
        const colors = [
            'rgba(249, 115, 22, 0.8)',
            'rgba(236, 72, 153, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(6, 182, 212, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(99, 102, 241, 0.8)',
        ];
        window.reportChartInstances.messagesByType = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.type),
                datasets: [{
                    data: data.map(d => d.total),
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, padding: 8, font: { size: 11 } }
                    }
                }
            }
        });
    };
    
    window.renderMessagesByChannel = function(data) {
        const ctx = document.getElementById('messagesByChannelChart');
        if (!ctx || !data || !data.length) return;
        window.destroyChart('messagesByChannel');
        const colors = [
            'rgba(34, 197, 94, 0.8)',
            'rgba(6, 182, 212, 0.8)',
            'rgba(99, 102, 241, 0.8)',
            'rgba(249, 115, 22, 0.8)',
            'rgba(236, 72, 153, 0.8)',
        ];
        window.reportChartInstances.messagesByChannel = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.name),
                datasets: [{
                    label: 'Mensajes',
                    data: data.map(d => d.total),
                    backgroundColor: colors.slice(0, data.length),
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    y: { grid: { display: false } }
                }
            }
        });
    };
    
    window.addEventListener('charts-updated', function(event) {
        if (event.detail && event.detail[0]) {
            window.renderAllCharts(event.detail[0]);
        }
    });

    // Función para exportar a PDF
    window.exportToPDF = function() {
        const selectedAgent = @json($userId);
        const selectedAgentName = document.querySelector('select[wire\\:model\\.live="userId"] option:checked')?.textContent || 'Todos los agentes';
        const dateFrom = @json($dateFrom);
        const dateTo = @json($dateTo);
        
        // Crear ventana de impresión
        const printWindow = window.open('', '_blank');
        
        // Obtener datos
        const totalMessages = {{ $this->totalMessages }};
        const totalConversations = {{ $this->totalConversations }};
        const totalIncoming = {{ $this->totalIncoming }};
        const totalOutgoing = {{ $this->totalOutgoing }};
        const messagesByUser = @json($this->messagesByUser);
        
        // Construir tabla de agentes
        let agentsTable = '';
        if (selectedAgent) {
            // Solo el agente seleccionado
            const agent = messagesByUser.find(u => u.id == selectedAgent);
            if (agent) {
                agentsTable = `<tr><td style="padding: 8px; border: 1px solid #ddd;">${agent.name}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${agent.total.toLocaleString()}</td></tr>`;
            }
        } else {
            // Todos los agentes
            messagesByUser.forEach((agent, index) => {
                agentsTable += `<tr><td style="padding: 8px; border: 1px solid #ddd;">${index + 1}. ${agent.name}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${agent.total.toLocaleString()}</td></tr>`;
            });
        }
        
        // Construir tabla de contactos
        let contactsTable = '';
        const contactRows = document.querySelectorAll('#contacts-table-section tbody tr');
        contactRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 10) {
                contactsTable += `<tr>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px;">${cells[0].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px;">${cells[1].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px;">${cells[2].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px;">${cells[3].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px;">${cells[4].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px;">${cells[5].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px;">${cells[6].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px; text-align: center;">${cells[7].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px; text-align: center;">${cells[8].textContent.trim()}</td>
                    <td style="padding: 6px; border: 1px solid #ddd; font-size: 11px; text-align: center;">${cells[9].textContent.trim()}</td>
                </tr>`;
            }
        });
        
        const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Reporte de Chats - ASESCO BPO</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
                    h1 { color: #f97316; margin-bottom: 5px; }
                    h2 { color: #374151; margin-top: 30px; border-bottom: 2px solid #f97316; padding-bottom: 5px; }
                    .header { border-bottom: 3px solid #f97316; padding-bottom: 15px; margin-bottom: 20px; }
                    .meta { color: #666; font-size: 14px; }
                    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                    .kpi-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; text-align: center; }
                    .kpi-value { font-size: 24px; font-weight: bold; color: #1f2937; }
                    .kpi-label { font-size: 12px; color: #6b7280; margin-top: 5px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    th { background: #f97316; color: white; padding: 10px; text-align: left; }
                    td { padding: 8px; border: 1px solid #ddd; }
                    tr:nth-child(even) { background: #f9fafb; }
                    .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>ASESCO BPO - Reporte de Chats Atendidos</h1>
                    <p class="meta">
                        <strong>Período:</strong> ${dateFrom} al ${dateTo}<br>
                        <strong>Agente:</strong> ${selectedAgentName}<br>
                        <strong>Generado:</strong> ${new Date().toLocaleString('es-CO')}
                    </p>
                </div>
                
                <h2>Resumen General</h2>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-value">${totalMessages.toLocaleString()}</div>
                        <div class="kpi-label">Total Mensajes</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">${totalConversations.toLocaleString()}</div>
                        <div class="kpi-label">Conversaciones</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">${totalIncoming.toLocaleString()}</div>
                        <div class="kpi-label">Recibidos</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">${totalOutgoing.toLocaleString()}</div>
                        <div class="kpi-label">Enviados</div>
                    </div>
                </div>
                
                <h2>${selectedAgent ? 'Agente Seleccionado' : 'Ranking de Agentes (Mensajes Enviados)'}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Agente</th>
                            <th style="text-align: center;">Mensajes Enviados</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${agentsTable || '<tr><td colspan="2" style="text-align: center; padding: 20px;">Sin datos de agentes</td></tr>'}
                    </tbody>
                </table>
                
                <h2>Detalle de Contactos</h2>
                <table>
                    <thead>
                        <tr>
                            <th style="font-size: 11px;">ID</th>
                            <th style="font-size: 11px;">Nombre</th>
                            <th style="font-size: 11px;">WhatsApp</th>
                            <th style="font-size: 11px;">Etiquetas</th>
                            <th style="font-size: 11px;">Último Contacto</th>
                            <th style="font-size: 11px;">Creación</th>
                            <th style="font-size: 11px;">Agente</th>
                            <th style="font-size: 11px; text-align: center;">Env.</th>
                            <th style="font-size: 11px; text-align: center;">Rec.</th>
                            <th style="font-size: 11px; text-align: center;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${contactsTable || '<tr><td colspan="10" style="text-align: center; padding: 20px;">Sin datos de contactos</td></tr>'}
                    </tbody>
                </table>
                
                <div class="footer">
                    <p>Este reporte fue generado automáticamente por el sistema ASESCO BPO.</p>
                </div>
            </body>
            </html>
        `;
        
        printWindow.document.write(html);
        printWindow.document.close();
        
        // Esperar a que cargue y luego imprimir
        printWindow.onload = function() {
            printWindow.print();
        };
    };
</script>
@endscript
