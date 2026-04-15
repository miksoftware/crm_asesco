<div x-data="{
    messagesChart: null,
    initCharts() {
        this.renderMessagesChart();
    },
    renderMessagesChart() {
        const ctx = document.getElementById('messagesChart');
        if (!ctx) return;
        if (this.messagesChart) this.messagesChart.destroy();

        const data = @js($this->messagesChart);
        this.messagesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Enviados',
                        data: data.outgoing,
                        backgroundColor: 'rgba(249, 115, 22, 0.8)',
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Recibidos',
                        data: data.incoming,
                        backgroundColor: 'rgba(236, 72, 153, 0.8)',
                        borderRadius: 6,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
}" x-init="initCharts()" @period-changed.window="$nextTick(() => renderMessagesChart())">

    {{-- Saludo y selector de período --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">
                {{ $isAdmin ? '📊 Panel General' : '👋 Hola, ' . auth()->user()->name }}
            </h2>
            <p class="text-gray-500 text-sm mt-1">
                {{ $isAdmin ? 'Resumen de actividad de todo el equipo' : '¡Aquí tienes tu resumen de actividad!' }}
            </p>
        </div>
        <div class="flex items-center gap-2 bg-white rounded-xl shadow-sm p-1">
            @foreach (['today' => 'Hoy', 'yesterday' => 'Ayer', 'week' => 'Semana', 'month' => 'Mes'] as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $period === $key ? 'gradient-bg text-white shadow-md' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
        {{-- Mensajes Enviados --}}
        @php $ms = $this->messagesSent; @endphp
        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-primary-500 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Mensajes Enviados</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($ms['value']) }}</p>
                    @if ($ms['change'] !== null)
                        <p class="{{ $ms['change'] >= 0 ? 'text-green-500' : 'text-red-500' }} text-sm mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1 {{ $ms['change'] < 0 ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                            </svg>
                            {{ abs($ms['change']) }}% vs anterior
                        </p>
                    @endif
                </div>
                <div class="w-14 h-14 rounded-xl bg-primary-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Mensajes Recibidos --}}
        @php $mr = $this->messagesReceived; @endphp
        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-secondary-500 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Mensajes Recibidos</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($mr['value']) }}</p>
                    @if ($mr['change'] !== null)
                        <p class="{{ $mr['change'] >= 0 ? 'text-green-500' : 'text-red-500' }} text-sm mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1 {{ $mr['change'] < 0 ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                            </svg>
                            {{ abs($mr['change']) }}% vs anterior
                        </p>
                    @endif
                </div>
                <div class="w-14 h-14 rounded-xl bg-secondary-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-secondary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Promesas de Pago --}}
        @php $pp = $this->paymentPromisesToday; @endphp
        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Promesas de Pago</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">${{ number_format($pp['amount'], 0, ',', '.') }}</p>
                    <p class="text-gray-400 text-xs mt-1">{{ $pp['count'] }} {{ $pp['count'] === 1 ? 'promesa' : 'promesas' }}</p>
                    @if ($pp['change'] !== null)
                        <p class="{{ $pp['change'] >= 0 ? 'text-green-500' : 'text-red-500' }} text-sm mt-1 flex items-center">
                            <svg class="w-4 h-4 mr-1 {{ $pp['change'] < 0 ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                            </svg>
                            {{ abs($pp['change']) }}% vs anterior
                        </p>
                    @endif
                </div>
                <div class="w-14 h-14 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Conversaciones Efectivas --}}
        @php $ec = $this->effectiveConversations; @endphp
        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Conv. Efectivas</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($ec['value']) }}</p>
                    @if ($ec['change'] !== null)
                        <p class="{{ $ec['change'] >= 0 ? 'text-green-500' : 'text-red-500' }} text-sm mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1 {{ $ec['change'] < 0 ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                            </svg>
                            {{ abs($ec['change']) }}% vs anterior
                        </p>
                    @endif
                </div>
                <div class="w-14 h-14 rounded-xl bg-blue-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Seguimientos pendientes banner --}}
    @if ($this->pendingFollowUps > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-amber-800 font-medium">
                    Tienes {{ $this->pendingFollowUps }} {{ $this->pendingFollowUps === 1 ? 'seguimiento pendiente' : 'seguimientos pendientes' }} para hoy
                </p>
                <p class="text-amber-600 text-sm">Revisa tus seguimientos programados</p>
            </div>
        </div>
    @endif

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Gráfico de mensajes (7 días) --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">📈 Mensajes - Últimos 7 días</h3>
            </div>
            <div class="p-5">
                <div style="height: 280px;">
                    <canvas id="messagesChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Resumen de promesas por estado --}}
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">💰 Estado de Promesas</h3>
            </div>
            <div class="p-5 space-y-4">
                @php $ps = $this->promiseSummary; @endphp

                {{-- Pendientes --}}
                <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Pendientes</p>
                            <p class="text-xs text-gray-500">{{ $ps['pending']['count'] }} promesas</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-amber-600">${{ number_format($ps['pending']['amount'], 0, ',', '.') }}</p>
                </div>

                {{-- Cumplidas --}}
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Cumplidas</p>
                            <p class="text-xs text-gray-500">{{ $ps['fulfilled']['count'] }} promesas</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-green-600">${{ number_format($ps['fulfilled']['amount'], 0, ',', '.') }}</p>
                </div>

                {{-- Incumplidas --}}
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Incumplidas</p>
                            <p class="text-xs text-gray-500">{{ $ps['broken']['count'] }} promesas</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-red-600">${{ number_format($ps['broken']['amount'], 0, ',', '.') }}</p>
                </div>

                {{-- Tasa de cumplimiento --}}
                @php
                    $totalPromises = $ps['pending']['count'] + $ps['fulfilled']['count'] + $ps['broken']['count'];
                    $fulfillRate = $totalPromises > 0 ? round(($ps['fulfilled']['count'] / $totalPromises) * 100, 1) : 0;
                @endphp
                <div class="pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-600">Tasa de cumplimiento</span>
                        <span class="text-sm font-bold {{ $fulfillRate >= 50 ? 'text-green-600' : 'text-amber-600' }}">{{ $fulfillRate }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full {{ $fulfillRate >= 50 ? 'bg-green-500' : 'bg-amber-500' }} transition-all" style="width: {{ $fulfillRate }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sección para agentes (no admin): Mi Rendimiento --}}
    @if (! $isAdmin)
        @php $perf = $this->myPerformance; @endphp
        <div class="bg-white rounded-xl shadow-sm mb-6">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">🏆 Mi Rendimiento</h3>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-5">
                <div class="text-center p-4 bg-gradient-to-br from-primary-50 to-primary-100 rounded-xl">
                    <p class="text-3xl font-bold text-primary-600">{{ number_format($perf['messages_today']) }}</p>
                    <p class="text-xs text-gray-600 mt-1">Mensajes hoy</p>
                </div>
                <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl">
                    <p class="text-3xl font-bold text-blue-600">{{ number_format($perf['contacts_today']) }}</p>
                    <p class="text-xs text-gray-600 mt-1">Contactos hoy</p>
                </div>
                <div class="text-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl">
                    <p class="text-3xl font-bold text-green-600">{{ $perf['promises_month_count'] }}</p>
                    <p class="text-xs text-gray-600 mt-1">Promesas este mes</p>
                </div>
                <div class="text-center p-4 bg-gradient-to-br from-secondary-50 to-secondary-100 rounded-xl">
                    <p class="text-3xl font-bold text-secondary-600">{{ $perf['followups_completed'] }}</p>
                    <p class="text-xs text-gray-600 mt-1">Seguimientos completados</p>
                </div>
            </div>
            <div class="px-5 pb-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($perf['messages_week']) }}</p>
                            <p class="text-xs text-gray-500">Mensajes esta semana</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($perf['messages_month']) }}</p>
                            <p class="text-xs text-gray-500">Mensajes este mes</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">${{ number_format($perf['promises_month_amount'], 0, ',', '.') }}</p>
                            <p class="text-xs text-gray-500">Valor promesas mes</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Segunda fila: Ranking (admin) / Promesas del día + Seguimientos --}}
    <div class="grid grid-cols-1 lg:grid-cols-{{ $isAdmin ? '3' : '2' }} gap-6 mb-6">

        {{-- Ranking de agentes (solo admin) --}}
        @if ($isAdmin)
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">🏅 Ranking de Agentes</h3>
                </div>
                <div class="p-5">
                    @php $ranking = $this->agentRanking; @endphp
                    @if (count($ranking) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 text-xs uppercase tracking-wider">
                                        <th class="pb-3 pr-4">#</th>
                                        <th class="pb-3 pr-4">Agente</th>
                                        <th class="pb-3 pr-4 text-center">Mensajes</th>
                                        <th class="pb-3 pr-4 text-center">Contactos</th>
                                        <th class="pb-3 pr-4 text-center">Promesas</th>
                                        <th class="pb-3 text-right">Valor Promesas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($ranking as $agent)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="py-3 pr-4">
                                                @if ($agent['position'] === 1)
                                                    <span class="text-xl">🥇</span>
                                                @elseif ($agent['position'] === 2)
                                                    <span class="text-xl">🥈</span>
                                                @elseif ($agent['position'] === 3)
                                                    <span class="text-xl">🥉</span>
                                                @else
                                                    <span class="text-gray-400 font-medium">{{ $agent['position'] }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pr-4 font-medium text-gray-800">{{ $agent['name'] }}</td>
                                            <td class="py-3 pr-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700">
                                                    {{ number_format($agent['messages_sent']) }}
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                    {{ number_format($agent['contacts_attended']) }}
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    {{ $agent['promise_count'] }}
                                                </span>
                                            </td>
                                            <td class="py-3 text-right font-semibold text-green-600">
                                                ${{ number_format($agent['promise_total'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p>Sin actividad en este período</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Promesas del día + Seguimientos --}}
        <div class="bg-white rounded-xl shadow-sm {{ !$isAdmin ? 'lg:col-span-1' : '' }}">
            <div class="p-5 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">📅 Promesas para Hoy</h3>
                    <span class="text-sm font-bold text-green-600">${{ number_format($this->promisesDueTodayTotal, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="p-5">
                @php $todayPromises = $this->promisesDueToday; @endphp
                @if (count($todayPromises) > 0)
                    <div class="space-y-3">
                        @foreach ($todayPromises as $promise)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $promise['contact_name'] }}</p>
                                    @if ($isAdmin)
                                        <p class="text-xs text-gray-500">{{ $promise['user_name'] }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-green-600">${{ number_format($promise['amount'], 0, ',', '.') }}</span>
                                    @if ($promise['message_sent'])
                                        <span class="w-2 h-2 rounded-full bg-green-400" title="Mensaje enviado"></span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-gray-300" title="Sin mensaje"></span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6 text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm">No hay promesas para hoy</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Seguimientos próximos (solo para agentes) --}}
        @if (! $isAdmin)
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">⏰ Próximos Seguimientos</h3>
                </div>
                <div class="p-5">
                    @php $followUps = $this->upcomingFollowUps; @endphp
                    @if (count($followUps) > 0)
                        <div class="space-y-3">
                            @foreach ($followUps as $fu)
                                <div class="flex items-start gap-3 p-3 rounded-lg {{ $fu['is_overdue'] ? 'bg-red-50 border border-red-100' : ($fu['is_today'] ? 'bg-amber-50 border border-amber-100' : 'bg-gray-50') }}">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 {{ $fu['is_overdue'] ? 'bg-red-100' : ($fu['is_today'] ? 'bg-amber-100' : 'bg-blue-100') }}">
                                        @if ($fu['is_overdue'])
                                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 {{ $fu['is_today'] ? 'text-amber-500' : 'text-blue-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ $fu['contact_name'] }}</p>
                                        @if ($fu['note'])
                                            <p class="text-xs text-gray-500 truncate">{{ $fu['note'] }}</p>
                                        @endif
                                        <p class="text-xs {{ $fu['is_overdue'] ? 'text-red-500 font-medium' : 'text-gray-400' }} mt-1">
                                            {{ \Carbon\Carbon::parse($fu['scheduled_date'])->translatedFormat('D d M, h:i A') }}
                                            @if ($fu['is_overdue'])
                                                · Vencido
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6 text-gray-400">
                            <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm">Sin seguimientos próximos</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Tercera fila: Canales (admin) + Actividad reciente --}}
    <div class="grid grid-cols-1 lg:grid-cols-{{ $isAdmin ? '3' : '1' }} gap-6">

        {{-- Estado de canales (solo admin) --}}
        @if ($isAdmin)
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">📡 Estado de Canales</h3>
                </div>
                <div class="p-5 space-y-3">
                    @php $channels = $this->channelStatuses; @endphp
                    @forelse ($channels as $channel)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full" style="background-color: {{ $channel['status_color'] }}"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $channel['name'] }}</p>
                                    @if ($channel['phone'])
                                        <p class="text-xs text-gray-500">{{ $channel['phone'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs font-medium px-2.5 py-1 rounded-full"
                                style="background-color: {{ $channel['status_color'] }}20; color: {{ $channel['status_color'] }}">
                                {{ $channel['status_label'] }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-400">
                            <p class="text-sm">No hay canales activos</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Actividad reciente --}}
        <div class="{{ $isAdmin ? 'lg:col-span-2' : '' }} bg-white rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">🕐 Actividad Reciente</h3>
            </div>
            <div class="p-5">
                @php $activities = $this->recentActivity; @endphp
                @if (count($activities) > 0)
                    <div class="space-y-3">
                        @foreach ($activities as $activity)
                            <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                                    {{ $activity['color'] === 'green' ? 'bg-green-100' : 'bg-blue-100' }}">
                                    @if ($activity['icon'] === 'dollar')
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $activity['text'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($activity['time'])->diffForHumans() }}
                                    </p>
                                </div>
                                @if ($activity['detail'])
                                    <span class="text-green-600 font-semibold text-sm">{{ $activity['detail'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p>Sin actividad reciente</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
