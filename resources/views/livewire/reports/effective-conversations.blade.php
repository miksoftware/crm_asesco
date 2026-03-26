<div class="space-y-6" x-data="{ ready: false }" x-init="setTimeout(() => { ready = true; initReportCharts(); }, 100)">
    <!-- Header with Quick Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Reporte de Conversaciones Efectivas</h1>
                <p class="text-gray-500 text-sm mt-1">Conteo de contactos con los que hubo interacción bilateral por agente</p>
            </div>
            
            <div class="flex flex-wrap gap-2">
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
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
                <label class="block text-xs font-medium text-gray-500 mb-1">Agrupar gráfico por</label>
                <select wire:model.live="groupBy" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="day">Día</option>
                    <option value="week">Semana</option>
                    <option value="month">Mes</option>
                </select>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm border border-orange-400 p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                    </svg>
                </div>
            </div>
            <p class="text-4xl font-bold mt-4">{{ number_format($this->totalEffectiveConversations) }}</p>
            <p class="text-sm font-medium opacity-90">Conversaciones Efectivas Globales</p>
            <p class="text-xs opacity-75 mt-1">Suma de contactos únicos atendidos en este período</p>
        </div>

        <!-- Promedio por día -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-4xl font-bold text-gray-800 mt-4">{{ number_format($this->avgConversationsPerDay, 1) }}</p>
            <p class="text-sm font-medium text-gray-500">Promedio Diario</p>
            <p class="text-xs text-gray-400 mt-1">Conversaciones por día en el rango de fechas</p>
        </div>

        <!-- Mejor Agente -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                </div>
            </div>
            @if($this->topAgent)
                <p class="text-2xl font-bold text-gray-800 mt-4 truncate">{{ $this->topAgent['name'] }}</p>
                <p class="text-sm font-medium text-gray-500">Mejor Agente ({{ number_format($this->topAgent['total']) }} conv.)</p>
                <p class="text-xs text-gray-400 mt-1">Con más interacciones bilaterales</p>
            @else
                <p class="text-2xl font-bold text-gray-800 mt-4">-</p>
                <p class="text-sm font-medium text-gray-500">Mejor Agente</p>
                <p class="text-xs text-gray-400 mt-1">Sin datos</p>
            @endif
        </div>
    </div>

    <!-- Info Banner -->
    <div class="bg-blue-50 rounded-xl shadow-sm border border-blue-100 p-4 flex gap-4 mt-2">
        <div class="flex-shrink-0 mt-1">
            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-blue-900">¿Qué es una conversación efectiva?</h3>
            <p class="text-sm text-blue-800 mt-1">
                A diferencia del conteo simple de mensajes sueltos que ves en "Chats Atendidos", el sistema contabiliza una "Conversación Efectiva" para un Agente solo cuando interactúa realmente: <b>el agente envió al menos 1 mensaje a cierto contacto, Y ese contacto respondió con al menos 1 mensaje dentro del mismo rango de fechas.</b> Los mensajes automáticos de sistema que no están atados a un agente no entran en este conteo.
            </p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Conversaciones en el Tiempo</h3>
                    <p class="text-xs text-gray-500">Evolución según agrupamiento (Día, Semana, Mes)</p>
                </div>
            </div>
            <div class="h-80" wire:ignore>
                <canvas id="conversationsOverTimeChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Ranking por Agente</h3>
                <p class="text-xs text-gray-500">Volumen de conversaciones efectivas</p>
            </div>
            <div class="space-y-3 max-h-80 overflow-y-auto pr-2">
                @forelse($this->effectiveConversationsByUser as $index => $user)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-orange-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background-color: {{ $user['color'] }}">
                                {{ $index + 1 }}
                            </span>
                            <span class="text-sm font-semibold text-gray-700">{{ $user['name'] }}</span>
                        </div>
                        <span class="text-lg font-bold text-gray-800">{{ number_format($user['total']) }}</span>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <p class="text-sm text-gray-500">Sin conversaciones registradas</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div wire:loading.flex class="fixed inset-0 bg-black/20 backdrop-blur-sm z-50 items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl p-6 flex items-center gap-3">
            <svg class="animate-spin h-6 w-6 text-orange-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-700 font-medium">Actualizando reporte...</span>
        </div>
    </div>
</div>

@script
<script>
    window.effChartInstance = null;
    
    window.initReportCharts = function() {
        const data = @json($this->effectiveConversationsByDate);
        window.renderEffectiveOverTime(data);
    };
    
    window.renderEffectiveOverTime = function(data) {
        const ctx = document.getElementById('conversationsOverTimeChart');
        if (!ctx || !data) return;
        
        if(window.effChartInstance) {
            window.effChartInstance.destroy();
        }
        
        window.effChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Conv. Efectivas',
                    data: data.data || [],
                    borderColor: 'rgb(249, 115, 22)',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgb(249, 115, 22)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
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
    
    window.addEventListener('charts-updated', function(event) {
        if (event.detail && event.detail[0] && event.detail[0].conversationsByDate) {
            window.renderEffectiveOverTime(event.detail[0].conversationsByDate);
        }
    });
</script>
@endscript
