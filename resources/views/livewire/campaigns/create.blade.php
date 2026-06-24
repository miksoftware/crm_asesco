<div class="p-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a campañas
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Nueva Campaña</h1>
        <p class="text-gray-600 mt-1">Crea una campaña de mensajería masiva</p>
    </div>

    {{-- Steps indicator --}}
    <div class="mb-8">
        <div class="flex items-center">
            @foreach([1 => 'Configuración', 2 => 'Mensaje', 3 => 'Destinatarios', 4 => 'Anti-ban'] as $step => $label)
                {{-- Step circle and label --}}
                <div class="flex flex-col items-center">
                    <button wire:click="goToStep({{ $step }})"
                            class="flex flex-col items-center gap-1 {{ $step <= $currentStep ? 'cursor-pointer' : 'cursor-not-allowed' }}"
                            {{ $step > $currentStep ? 'disabled' : '' }}>
                        <span class="flex items-center justify-center w-10 h-10 rounded-full border-2 text-sm font-semibold transition-all
                            {{ $currentStep === $step ? 'border-orange-500 bg-orange-500 text-white shadow-lg shadow-orange-200' : ($currentStep > $step ? 'border-orange-500 bg-orange-500 text-white' : 'border-gray-300 bg-white text-gray-400') }}">
                            @if($currentStep > $step)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                {{ $step }}
                            @endif
                        </span>
                        <span class="hidden sm:block text-xs font-medium mt-1 {{ $currentStep >= $step ? 'text-orange-600' : 'text-gray-400' }}">{{ $label }}</span>
                    </button>
                </div>
                
                {{-- Connector line --}}
                @if($step < 4)
                <div class="flex-1 mx-2 sm:mx-4">
                    <div class="h-1 rounded-full {{ $currentStep > $step ? 'bg-orange-500' : 'bg-gray-200' }}"></div>
                </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Form content --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        {{-- Step 1: Configuración básica --}}
        @if($currentStep === 1)
        <div class="space-y-6">
            <h2 class="text-lg font-semibold text-gray-900">Configuración básica</h2>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de la campaña *</label>
                <input type="text" 
                       wire:model="name"
                       placeholder="Ej: Recordatorio de pago enero"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Canal de WhatsApp *</label>
                <select wire:model="channelId"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Seleccionar canal...</option>
                    @foreach($this->channels as $channel)
                    <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                    @endforeach
                </select>
                @error('channelId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                
                @if($this->channels->isEmpty())
                <p class="mt-2 text-sm text-yellow-600">
                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    No hay canales conectados disponibles
                </p>
                @endif
            </div>
        </div>
        @endif

        {{-- Step 2: Mensaje --}}
        @if($currentStep === 2)
        <div class="space-y-6">
            <h2 class="text-lg font-semibold text-gray-900">Contenido del mensaje</h2>

            {{-- Templates --}}
            @if($this->templates->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Plantillas predefinidas</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($this->templates as $template)
                    <button type="button"
                            wire:click="selectTemplate({{ $template->id }})"
                            class="p-3 text-left border rounded-lg transition-all {{ $templateId === $template->id ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-gray-300' }}">
                        <p class="font-medium text-gray-900">{{ $template->name }}</p>
                        <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ Str::limit($template->content, 80) }}</p>
                    </button>
                    @endforeach
                </div>
                @if($templateId)
                <button type="button" wire:click="clearTemplate" class="mt-2 text-sm text-gray-500 hover:text-gray-700">
                    Limpiar selección
                </button>
                @endif
            </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mensaje *</label>
                <textarea wire:model="messageContent"
                          rows="6"
                          placeholder="Escribe tu mensaje aquí..."
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"></textarea>
                @error('messageContent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                
                <div class="mt-2 p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-800 font-medium">Variables disponibles:</p>
                    <ul class="text-sm text-blue-700 mt-1 space-y-1">
                        <li><code class="bg-blue-100 px-1 rounded">{nombre}</code> - Nombre del contacto</li>
                        <li><code class="bg-blue-100 px-1 rounded">{val1}</code> - Variable personalizada 1</li>
                        <li><code class="bg-blue-100 px-1 rounded">{val2}</code> - Variable personalizada 2</li>
                    </ul>
                </div>
            </div>

            {{-- Preview --}}
            @if(!empty($messageContent))
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Vista previa</label>
                <div class="p-4 bg-gray-100 rounded-lg">
                    <div class="bg-green-100 rounded-lg p-3 max-w-xs ml-auto">
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ str_replace(['{nombre}', '{val1}', '{val2}'], ['Juan Pérez', '$150.000', '15/02/2026'], $messageContent) }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- Step 3: Destinatarios --}}
        @if($currentStep === 3)
        <div class="space-y-6">
            <h2 class="text-lg font-semibold text-gray-900">Destinatarios</h2>

            {{-- CSV Upload --}}
            <div x-data="{ isDragging: false }">
                <label class="block text-sm font-medium text-gray-700 mb-2">Cargar archivo CSV</label>
                <div class="border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                     :class="isDragging ? 'border-orange-500 bg-orange-50' : 'border-gray-300 hover:border-gray-400'"
                     x-on:dragover.prevent="isDragging = true"
                     x-on:dragleave.prevent="isDragging = false"
                     x-on:drop.prevent="
                         isDragging = false;
                         const file = $event.dataTransfer.files[0];
                         if (file && (file.name.endsWith('.csv') || file.name.endsWith('.txt'))) {
                             $refs.csvInput.files = $event.dataTransfer.files;
                             $refs.csvInput.dispatchEvent(new Event('change', { bubbles: true }));
                         }
                     ">
                    <input type="file" 
                           wire:model="csvFile"
                           accept=".csv,.txt"
                           class="hidden"
                           id="csvUpload"
                           x-ref="csvInput">
                    <label for="csvUpload" class="cursor-pointer">
                        <svg class="mx-auto h-12 w-12" :class="isDragging ? 'text-orange-500' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="mt-2 text-sm" :class="isDragging ? 'text-orange-600' : 'text-gray-600'">
                            <span x-show="!isDragging">Arrastra un archivo CSV o haz clic para seleccionar</span>
                            <span x-show="isDragging">Suelta el archivo aquí</span>
                        </p>
                        <p class="mt-1 text-xs text-gray-500">Columnas: telefono, nombre, val1, val2</p>
                    </label>
                </div>
                
                {{-- Loading indicator --}}
                <div wire:loading wire:target="csvFile" class="mt-2 flex items-center gap-2 text-sm text-orange-600">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Procesando archivo...
                </div>
                
                <a href="{{ asset('examples/campaign_example.csv') }}" 
                   download
                   class="inline-flex items-center gap-1 mt-2 text-sm text-orange-600 hover:text-orange-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar ejemplo CSV
                </a>
            </div>

            {{-- Manual input --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">O ingresa números manualmente</label>
                <div class="flex gap-2">
                    <textarea wire:model="manualNumbers"
                              rows="3"
                              placeholder="Ingresa números separados por coma o salto de línea&#10;Ej: 3001234567, 3109876543"
                              class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"></textarea>
                    <button type="button"
                            wire:click="parseManualNumbers"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors self-end">
                        Agregar
                    </button>
                </div>
            </div>

            {{-- Recipients list --}}
            @if(!empty($recipients))
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">
                        Destinatarios cargados: {{ count($recipients) }}
                    </label>
                    <button type="button"
                            wire:click="clearRecipients"
                            wire:confirm="¿Eliminar todos los destinatarios?"
                            class="text-sm text-red-600 hover:text-red-700">
                        Limpiar todo
                    </button>
                </div>
                <div class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Teléfono</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Nombre</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Val1</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Val2</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach(array_slice($recipients, 0, 100) as $index => $recipient)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono">{{ $recipient['phone_number'] }}</td>
                                <td class="px-4 py-2">{{ $recipient['name'] ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $recipient['val1'] ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $recipient['val2'] ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    <button type="button"
                                            wire:click="removeRecipient({{ $index }})"
                                            class="text-red-500 hover:text-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(count($recipients) > 100)
                    <p class="px-4 py-2 text-sm text-gray-500 bg-gray-50">
                        Mostrando 100 de {{ count($recipients) }} destinatarios
                    </p>
                    @endif
                </div>
            </div>
            @else
            <div class="text-center py-8 text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <p class="mt-2">No hay destinatarios cargados</p>
            </div>
            @endif
        </div>
        @endif

        {{-- Step 4: Anti-ban config --}}
        @if($currentStep === 4)
        <div class="space-y-6">
            <h2 class="text-lg font-semibold text-gray-900">Configuración Anti-Ban</h2>
            
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex gap-3">
                    <svg class="w-6 h-6 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-medium text-red-800">⚠️ Configuración crítica para evitar bloqueos</p>
                        <ul class="text-sm text-red-700 mt-1 space-y-1 list-disc list-inside">
                            <li>Delays muy bajos (menos de 8 s) aumentan el riesgo de bloqueo por WhatsApp.</li>
                            <li>Lotes grandes sin pausa suficiente activan los filtros anti-spam.</li>
                            <li>Recomendado: 8–20 s de delay, lotes de 20–30 mensajes, pausa de 5+ minutos.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Delay mínimo entre mensajes (segundos)
                    </label>
                    <input type="number" 
                           wire:model="delayMin"
                           min="5" max="60"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <p class="mt-1 text-xs text-gray-500">Mínimo permitido: 5 s · Recomendado: 8–12 s</p>
                    @error('delayMin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Delay máximo entre mensajes (segundos)
                    </label>
                    <input type="number" 
                           wire:model="delayMax"
                           min="10" max="120"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <p class="mt-1 text-xs text-gray-500">Mínimo permitido: 10 s · Recomendado: 15–25 s</p>
                    @error('delayMax') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Mensajes por lote
                    </label>
                    <input type="number" 
                           wire:model="batchSize"
                           min="5" max="100"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <p class="mt-1 text-xs text-gray-500">Recomendado: 20–30 mensajes por lote</p>
                    @error('batchSize') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Pausa entre lotes (segundos)
                    </label>
                    <input type="number" 
                           wire:model="batchPause"
                           min="60" max="1800"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <p class="mt-1 text-xs text-gray-500">Recomendado: 300–600 s (5–10 minutos)</p>
                    @error('batchPause') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Resumen --}}
            <div class="p-4 bg-gray-50 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2">Resumen de la campaña</h3>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Nombre</dt>
                        <dd class="font-medium text-gray-900">{{ $name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Destinatarios</dt>
                        <dd class="font-medium text-gray-900">{{ count($recipients) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tiempo estimado</dt>
                        <dd class="font-medium text-gray-900">
                            @php
                                $avgDelay = ($delayMin + $delayMax) / 2;
                                $batches = ceil(count($recipients) / $batchSize);
                                $totalSeconds = (count($recipients) * $avgDelay) + (($batches - 1) * $batchPause);
                                $hours = floor($totalSeconds / 3600);
                                $minutes = floor(($totalSeconds % 3600) / 60);
                            @endphp
                            @if($hours > 0)
                                {{ $hours }}h {{ $minutes }}min
                            @else
                                {{ $minutes }} minutos
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Velocidad aprox.</dt>
                        <dd class="font-medium text-gray-900">
                            {{ round(3600 / (($delayMin + $delayMax) / 2)) }} msgs/hora
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        @endif

        {{-- Navigation buttons --}}
        <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-200">
            <div>
                @if($currentStep > 1)
                <button type="button"
                        wire:click="previousStep"
                        class="px-4 py-2 text-gray-700 hover:text-gray-900 transition-colors">
                    ← Anterior
                </button>
                @endif
            </div>

            <div class="flex gap-3">
                @if($currentStep < 4)
                <button type="button"
                        wire:click="nextStep"
                        class="px-6 py-2 bg-gradient-to-r from-orange-500 to-pink-500 text-white rounded-lg hover:from-orange-600 hover:to-pink-600 transition-all">
                    Siguiente →
                </button>
                @else
                <button type="button"
                        wire:click="createCampaign(false)"
                        wire:loading.attr="disabled"
                        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="createCampaign">Guardar borrador</span>
                    <span wire:loading wire:target="createCampaign">Guardando...</span>
                </button>
                <button type="button"
                        wire:click="createCampaign(true)"
                        wire:loading.attr="disabled"
                        class="px-6 py-2 bg-gradient-to-r from-orange-500 to-pink-500 text-white rounded-lg hover:from-orange-600 hover:to-pink-600 transition-all disabled:opacity-50">
                    <span wire:loading.remove wire:target="createCampaign">Crear e iniciar</span>
                    <span wire:loading wire:target="createCampaign">Procesando...</span>
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
