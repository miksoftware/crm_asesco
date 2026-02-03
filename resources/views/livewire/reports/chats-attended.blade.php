<div class="space-y-6" x-data="reportCharts()">
    <!-- Header with Quick Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Reporte de Chats Atendidos</h1>
                <p class="text-gray-500 text-sm mt-1">Análisis detallado de la actividad de mensajería</p>
            </div>
            
            <!-- Quick Date Buttons -->
            <div class="flex flex-wrap gap-2">
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
                <button wire:click="setQuickDate('quarter')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                    Últimos 3 Meses
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
            <!-- Date From -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                <input type="date" wire:model.live="dateFrom" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            
            <!-- Date To -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                <input type="date" wire:model.live="dateTo" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            
            <!-- User Filter -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Usuario</label>
                <select wire:model.live="userId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Todos los usuarios</option>
                    @foreach($this->users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Channel Filter -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Canal</label>
                <select wire:model.live="channelId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Todos los canales</option>
                    @foreach($this->channels as $channel)
                        <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Direction Filter -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Dirección</label>
                <select wire:model.live="messageDirection" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="all">Todos</option>
                    <option value="incoming">Recibidos</option>
                    <option value="outgoing">Enviados</option>
                </select>
            </div>
            
            <!-- Group By -->
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

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- Total Messages -->
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
        </div>

        <!-- Total Conversations -->
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
        </div>

        <!-- Incoming -->
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
        </div>

        <!-- Outgoing -->
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
        </div>

        <!-- Avg per Day -->
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
        </div>

        <!-- Response Rate -->
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
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Messages Over Time (Large Chart) -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Mensajes por {{ $groupBy === 'day' ? 'Día' : ($groupBy === 'week' ? 'Semana' : 'Mes') }}</h3>
            <div class="h-80">
                <canvas id="messagesOverTimeChart"></canvas>
            </div>
        </div>

        <!-- Messages by User -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Agentes</h3>
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
        <!-- Messages by Hour -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Actividad por Hora</h3>
            <div class="h-64">
                <canvas id="messagesByHourChart"></canvas>
            </div>
        </div>

        <!-- Messages by Day of Week -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Actividad por Día</h3>
            <div class="h-64">
                <canvas id="messagesByDayChart"></canvas>
            </div>
        </div>

        <!-- Messages by Type (Pie) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Tipos de Mensaje</h3>
            <div class="h-64">
                <canvas id="messagesByTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 3 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Messages by Channel -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Mensajes por Canal</h3>
            <div class="h-64">
                <canvas id="messagesByChannelChart"></canvas>
            </div>
        </div>

        <!-- Top Contacts Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Contactos</h3>
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
    Alpine.data('reportCharts', () => ({
        charts: {},
        
        init() {
            this.loadCharts();
            
            // Re-render charts when Livewire updates
            Livewire.hook('morph.updated', () => {
                this.$nextTick(() => this.loadCharts());
            });
        },
        
        loadCharts() {
            this.renderMessagesOverTime();
            this.renderMessagesByHour();
            this.renderMessagesByDay();
            this.renderMessagesByType();
            this.renderMessagesByChannel();
        },
        
        destroyChart(id) {
            if (this.charts[id]) {
                this.charts[id].destroy();
                delete this.charts[id];
            }
        },
        
        renderMessagesOverTime() {
            const ctx = document.getElementById('messagesOverTimeChart');
            if (!ctx) return;
            
            this.destroyChart('messagesOverTime');
            
            const data = @json($this->messagesByDate);
            
            this.charts.messagesOverTime = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Mensajes',
                        data: data.data,
                        backgroundColor: 'rgba(249, 115, 22, 0.8)',
                        borderColor: 'rgb(249, 115, 22)',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        renderMessagesByHour() {
            const ctx = document.getElementById('messagesByHourChart');
            if (!ctx) return;
            
            this.destroyChart('messagesByHour');
            
            const data = @json($this->messagesByHour);
            
            this.charts.messagesByHour = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Mensajes',
                        data: data.data,
                        borderColor: 'rgb(236, 72, 153)',
                        backgroundColor: 'rgba(236, 72, 153, 0.1)',
                        fill: true,
                        tension: 0.4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        renderMessagesByDay() {
            const ctx = document.getElementById('messagesByDayChart');
            if (!ctx) return;
            
            this.destroyChart('messagesByDay');
            
            const data = @json($this->messagesByDayOfWeek);
            
            this.charts.messagesByDay = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Mensajes',
                        data: data.data,
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
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        renderMessagesByType() {
            const ctx = document.getElementById('messagesByTypeChart');
            if (!ctx) return;
            
            this.destroyChart('messagesByType');
            
            const data = @json($this->messagesByType);
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
            
            this.charts.messagesByType = new Chart(ctx, {
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
        },
        
        renderMessagesByChannel() {
            const ctx = document.getElementById('messagesByChannelChart');
            if (!ctx) return;
            
            this.destroyChart('messagesByChannel');
            
            const data = @json($this->messagesByChannel);
            const colors = [
                'rgba(34, 197, 94, 0.8)',
                'rgba(6, 182, 212, 0.8)',
                'rgba(99, 102, 241, 0.8)',
                'rgba(249, 115, 22, 0.8)',
                'rgba(236, 72, 153, 0.8)',
            ];
            
            this.charts.messagesByChannel = new Chart(ctx, {
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
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }));
</script>
@endscript
</div>
