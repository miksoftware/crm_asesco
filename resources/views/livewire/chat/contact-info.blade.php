<div class="h-full flex flex-col">
    <!-- Contact Header -->
    <div class="p-4 border-b border-gray-200 text-center">
        @php
            $channelId = $contact->channel_id;
        @endphp
        <div class="w-20 h-20 rounded-full bg-gray-200 flex items-center justify-center mx-auto mb-3 overflow-hidden">
            @if($contact->profile_picture)
                <img src="{{ $contact->profile_picture }}" alt="" class="w-full h-full object-cover">
            @else
                <span class="text-3xl font-semibold text-gray-500">
                    {{ strtoupper(substr($contact->display_name, 0, 1)) }}
                </span>
            @endif
        </div>
        
        @if($editing)
            <!-- Edit Mode -->
            <div class="space-y-2">
                <input type="text" 
                       wire:model="editName"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm"
                       placeholder="Nombre del contacto">
                <p class="text-sm text-gray-500">{{ $contact->phone_number }}</p>
                @if($contact->push_name && $contact->push_name !== $editName)
                    <p class="text-xs text-gray-400">WhatsApp: {{ $contact->push_name }}</p>
                @endif
            </div>
        @else
            <!-- View Mode -->
            <h3 class="font-semibold text-gray-900 text-lg">{{ $contact->display_name }}</h3>
            <p class="text-sm text-gray-500">{{ $contact->phone_number }}</p>
            @if($contact->push_name && $contact->push_name !== $contact->name)
                <p class="text-xs text-gray-400">WhatsApp: {{ $contact->push_name }}</p>
            @endif
        @endif
    </div>

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-y-auto">
        <!-- Labels Section -->
        <div class="p-4 border-b border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Etiquetas</h4>
            
            <!-- Current Labels with Remove Button -->
            <div class="flex flex-wrap gap-2 mb-3">
                @if(!empty($contact->labels))
                    @foreach($contact->labels as $labelValue)
                        @php
                            $labelEnum = App\Enums\ContactLabel::tryFrom($labelValue);
                        @endphp
                        @if($labelEnum)
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full font-medium"
                                  style="background-color: {{ $labelEnum->color() }}20; color: {{ $labelEnum->color() }}">
                                {{ $labelEnum->label() }}
                                @if($canManageLabels)
                                    <button wire:click="removeLabel('{{ $labelValue }}')"
                                            wire:loading.attr="disabled"
                                            class="ml-0.5 hover:opacity-70 transition-opacity"
                                            title="Quitar etiqueta">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                @endif
                            </span>
                        @endif
                    @endforeach
                @else
                    <span class="text-sm text-gray-400">Sin etiquetas</span>
                @endif
            </div>

            <!-- Add Label Dropdown -->
            @if($canManageLabels)
                @php
                    $currentLabels = $contact->labels ?? [];
                    $availableToAdd = collect($this->availableLabels)->filter(fn($label) => !in_array($label->value, $currentLabels));
                @endphp
                @if($availableToAdd->isNotEmpty())
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" 
                                type="button"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Agregar etiqueta
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10">
                            @foreach($availableToAdd as $label)
                                <button wire:click="addLabel('{{ $label->value }}')"
                                        @click="open = false"
                                        wire:loading.attr="disabled"
                                        class="w-full px-3 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2 transition-colors">
                                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $label->color() }}"></span>
                                    <span>{{ $label->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-400">Todas las etiquetas asignadas</p>
                @endif
            @endif
        </div>

        <!-- Quick Actions Section -->
        <livewire:chat.quick-actions :contact="$contact" :channel-id="$channelId" :key="'quick-actions-'.$contact->id" />

        <!-- Notes Section -->
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-gray-700">Notas</h4>
                @if(!$editing)
                    <button wire:click="startEditing" 
                            class="text-xs text-green-600 hover:text-green-700 font-medium">
                        Editar
                    </button>
                @endif
            </div>
            
            @if($editing)
                <textarea wire:model="editNotes"
                          rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm resize-none"
                          placeholder="Agregar notas sobre el contacto..."></textarea>
                
                <div class="flex justify-end gap-2 mt-2">
                    <button wire:click="cancelEditing"
                            class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="updateContact"
                            wire:loading.attr="disabled"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-green-500 hover:bg-green-600 rounded-lg transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="updateContact">Guardar</span>
                        <span wire:loading wire:target="updateContact">Guardando...</span>
                    </button>
                </div>
            @else
                @if($contact->notes)
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $contact->notes }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">Sin notas</p>
                @endif
            @endif
        </div>

        <!-- Conversation Summary -->
        <div class="p-4 border-b border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Resumen de Conversación</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Total mensajes:</span>
                    <span class="font-medium text-gray-700">{{ $this->totalMessages }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Primer contacto:</span>
                    <span class="font-medium text-gray-700">{{ $this->firstContactDate }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Mensajes enviados:</span>
                    <span class="font-medium text-gray-700">{{ $contact->messages()->where('direction', 'outgoing')->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Mensajes recibidos:</span>
                    <span class="font-medium text-gray-700">{{ $contact->messages()->where('direction', 'incoming')->count() }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Promises Section -->
        <div class="p-4 border-b border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Promesas de Pago</h4>
            
            @if($this->paymentPromises->isNotEmpty())
                <div class="space-y-2">
                    @foreach($this->paymentPromises as $promise)
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="font-semibold text-gray-900">${{ number_format($promise->promised_amount, 2) }}</span>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Fecha: {{ $promise->promised_date->format('d/m/Y') }}
                                    </p>
                                </div>
                                <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                    {{ $promise->status === 'fulfilled' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $promise->status === 'broken' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $promise->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                    @switch($promise->status)
                                        @case('fulfilled')
                                            Cumplida
                                            @break
                                        @case('broken')
                                            Incumplida
                                            @break
                                        @default
                                            Pendiente
                                    @endswitch
                                </span>
                            </div>
                            @if($promise->notes)
                                <p class="text-xs text-gray-600 mt-2 border-t border-gray-200 pt-2">{{ $promise->notes }}</p>
                            @endif
                            @if($promise->fulfilled_at)
                                <p class="text-xs text-green-600 mt-1">
                                    Cumplida el {{ $promise->fulfilled_at->format('d/m/Y H:i') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 italic">Sin promesas de pago registradas</p>
            @endif
        </div>

        <!-- Follow-ups Section -->
        <div class="p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Seguimientos</h4>
            
            @if($this->pendingFollowUps->isNotEmpty())
                <div class="mb-4">
                    <h5 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Pendientes</h5>
                    <div class="space-y-2">
                        @foreach($this->pendingFollowUps as $followUp)
                            <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-blue-700">
                                        {{ $followUp->scheduled_date->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                @if($followUp->note)
                                    <p class="text-xs text-gray-600 mt-2 pl-6">{{ $followUp->note }}</p>
                                @endif
                                @if($followUp->scheduled_date->isPast())
                                    <p class="text-xs text-red-500 mt-1 pl-6 font-medium">⚠️ Vencido</p>
                                @elseif($followUp->scheduled_date->isToday())
                                    <p class="text-xs text-orange-500 mt-1 pl-6 font-medium">📅 Hoy</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @php
                $completedFollowUps = $this->allFollowUps->where('status', '!=', 'pending')->take(5);
            @endphp
            
            @if($completedFollowUps->isNotEmpty())
                <div>
                    <h5 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Historial</h5>
                    <div class="space-y-2">
                        @foreach($completedFollowUps as $followUp)
                            <div class="p-2 bg-gray-50 rounded-lg text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">{{ $followUp->scheduled_date->format('d/m/Y') }}</span>
                                    <span class="px-2 py-0.5 text-xs rounded-full
                                        {{ $followUp->status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $followUp->status === 'completed' ? 'Completado' : 'Cancelado' }}
                                    </span>
                                </div>
                                @if($followUp->note)
                                    <p class="text-xs text-gray-500 mt-1">{{ Str::limit($followUp->note, 50) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->pendingFollowUps->isEmpty() && $completedFollowUps->isEmpty())
                <p class="text-sm text-gray-400 italic">Sin seguimientos programados</p>
            @endif
        </div>
    </div>
</div>
