<div class="h-[calc(100vh-4rem)] flex flex-col">
    @if($this->channels->isEmpty())
        <!-- No channels assigned -->
        <div class="flex-1 flex items-center justify-center bg-gray-50">
            <div class="text-center">
                <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">No tienes canales asignados</h3>
                <p class="text-gray-500 text-sm">Contacta al administrador para que te asigne un canal de WhatsApp.</p>
            </div>
        </div>
    @else
        <div class="flex-1 flex overflow-hidden">
            <!-- Left Column: Channel Selector & Conversations List -->
            <div class="w-80 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col">
                <!-- Channel Selector -->
                <div class="p-3 border-b border-gray-200">
                    <select wire:model.live="selectedChannelId" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        @foreach($this->channels as $channel)
                            <option value="{{ $channel->id }}">
                                {{ $channel->name }}
                                @if($channel->phone_number)
                                    ({{ $channel->phone_number }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Search & Filter -->
                <div class="p-3 border-b border-gray-200 space-y-2">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input wire:model.live.debounce.300ms="search" 
                               type="text" 
                               class="block w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm"
                               placeholder="Buscar contacto...">
                    </div>

                    <!-- Label Filter -->
                    <div class="flex items-center gap-2">
                        <select wire:model.live="labelFilter" 
                                class="flex-1 px-2 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-xs">
                            <option value="">Todas las etiquetas</option>
                            @foreach($this->availableLabels as $label)
                                <option value="{{ $label->value }}">{{ $label->label() }}</option>
                            @endforeach
                        </select>
                        @if($labelFilter)
                            <button wire:click="clearLabelFilter" 
                                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                    title="Limpiar filtro">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Conversations List -->
                <div class="flex-1 overflow-y-auto">
                    @forelse($this->conversations as $contact)
                        <div wire:click="selectConversation({{ $contact->id }})"
                             wire:key="contact-{{ $contact->id }}"
                             class="flex items-center gap-3 p-3 cursor-pointer border-b border-gray-100 hover:bg-gray-50 transition-colors {{ $selectedContactId === $contact->id ? 'bg-green-50 border-l-4 border-l-green-500' : '' }}">
                            <!-- Avatar -->
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                @if($contact->profile_picture)
                                    <img src="{{ $contact->profile_picture }}" alt="" class="w-full h-full object-cover">
                                @else
                                    <span class="text-lg font-semibold text-gray-500">
                                        {{ strtoupper(substr($contact->display_name, 0, 1)) }}
                                    </span>
                                @endif
                            </div>

                            <!-- Contact Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium text-gray-900 truncate">{{ $contact->display_name }}</h4>
                                    @if($contact->last_message)
                                        <span class="text-xs text-gray-400 flex-shrink-0 ml-2">
                                            {{ $contact->last_message->sent_at?->format('H:i') ?? $contact->last_message->created_at->format('H:i') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center justify-between mt-0.5">
                                    <p class="text-sm text-gray-500 truncate">
                                        @if($contact->last_message)
                                            @if($contact->last_message->direction === 'outgoing')
                                                <span class="text-gray-400">Tú: </span>
                                            @endif
                                            {{ Str::limit($contact->last_message->content ?? '[Media]', 30) }}
                                        @else
                                            Sin mensajes
                                        @endif
                                    </p>

                                    @if($contact->unread_count > 0)
                                        <span class="ml-2 px-2 py-0.5 bg-green-500 text-white text-xs font-medium rounded-full flex-shrink-0">
                                            {{ $contact->unread_count }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Labels -->
                                @if(!empty($contact->labels))
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($contact->labels as $labelValue)
                                            @php
                                                $labelEnum = App\Enums\ContactLabel::tryFrom($labelValue);
                                            @endphp
                                            @if($labelEnum)
                                                <span class="px-1.5 py-0.5 text-xs rounded-full"
                                                      style="background-color: {{ $labelEnum->color() }}20; color: {{ $labelEnum->color() }}">
                                                    {{ $labelEnum->label() }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">
                            @if($search || $labelFilter)
                                <p class="text-sm">No se encontraron conversaciones</p>
                                <button wire:click="$set('search', ''); $set('labelFilter', null)" 
                                        class="text-green-600 text-sm mt-2 hover:underline">
                                    Limpiar filtros
                                </button>
                            @else
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                <p class="text-sm">No hay conversaciones</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Center Column: Messages Area -->
            <div class="flex-1 flex flex-col bg-gray-100">
                @if($selectedContactId && $this->selectedContact)
                    <!-- Chat Header -->
                    <div class="bg-white px-4 py-3 border-b border-gray-200 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                            @if($this->selectedContact->profile_picture)
                                <img src="{{ $this->selectedContact->profile_picture }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="text-lg font-semibold text-gray-500">
                                    {{ strtoupper(substr($this->selectedContact->display_name, 0, 1)) }}
                                </span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">{{ $this->selectedContact->display_name }}</h3>
                            <p class="text-xs text-gray-500">{{ $this->selectedContact->phone_number }}</p>
                        </div>
                    </div>

                    <!-- Messages Container -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-3" 
                         id="messages-container"
                         x-data="{ 
                             isLoadingMore: false,
                             lastScrollHeight: 0,
                             scrollToBottom() {
                                 this.$nextTick(() => {
                                     this.$el.scrollTop = this.$el.scrollHeight;
                                 });
                             },
                             preserveScrollPosition() {
                                 // After loading more messages, maintain scroll position
                                 this.$nextTick(() => {
                                     const newScrollHeight = this.$el.scrollHeight;
                                     const scrollDiff = newScrollHeight - this.lastScrollHeight;
                                     this.$el.scrollTop = scrollDiff;
                                 });
                             },
                             handleScroll() {
                                 // Infinite scroll: load more when scrolled near top
                                 if (this.$el.scrollTop < 100 && !this.isLoadingMore && @js($hasMoreMessages)) {
                                     this.isLoadingMore = true;
                                     this.lastScrollHeight = this.$el.scrollHeight;
                                     $wire.loadMoreMessages().then(() => {
                                         this.preserveScrollPosition();
                                         this.isLoadingMore = false;
                                     });
                                 }
                             }
                         }"
                         x-init="scrollToBottom()"
                         @scroll.debounce.100ms="handleScroll()"
                         wire:poll.visible.5s>
                        
                        <!-- Load More Indicator -->
                        @if($hasMoreMessages)
                            <div class="text-center py-2" x-show="isLoadingMore">
                                <div class="inline-flex items-center gap-2 px-4 py-2 text-sm text-gray-500">
                                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span>Cargando mensajes anteriores...</span>
                                </div>
                            </div>
                            <div class="text-center" x-show="!isLoadingMore">
                                <button wire:click="loadMoreMessages" 
                                        wire:loading.attr="disabled"
                                        wire:target="loadMoreMessages"
                                        class="px-4 py-2 text-sm text-gray-600 bg-white rounded-full shadow hover:bg-gray-50 transition-colors">
                                    <span wire:loading.remove wire:target="loadMoreMessages">Cargar mensajes anteriores</span>
                                    <span wire:loading wire:target="loadMoreMessages">Cargando...</span>
                                </button>
                            </div>
                        @endif

                        <!-- Messages -->
                        @foreach($this->messages as $message)
                            <div wire:key="message-{{ $message->id }}"
                                 class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[70%] {{ $message->direction === 'outgoing' ? 'bg-green-500 text-white' : 'bg-white' }} rounded-2xl px-4 py-2 shadow-sm">
                                    <!-- Message Content -->
                                    @if($message->type === 'text')
                                        <p class="text-sm whitespace-pre-wrap break-words">{{ $message->content }}</p>
                                    @elseif($message->type === 'image')
                                        @if($message->media_url)
                                            <img src="{{ $message->media_url }}" alt="Imagen" class="max-w-full rounded-lg mb-1">
                                        @endif
                                        @if($message->content)
                                            <p class="text-sm">{{ $message->content }}</p>
                                        @endif
                                    @elseif($message->type === 'document')
                                        <div class="flex items-center gap-2">
                                            <svg class="w-8 h-8 {{ $message->direction === 'outgoing' ? 'text-white' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span class="text-sm">{{ $message->content ?? 'Documento' }}</span>
                                        </div>
                                    @elseif($message->type === 'audio')
                                        <div class="flex items-center gap-2">
                                            <svg class="w-6 h-6 {{ $message->direction === 'outgoing' ? 'text-white' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                                            </svg>
                                            <span class="text-sm">Audio</span>
                                        </div>
                                    @elseif($message->type === 'video')
                                        <div class="flex items-center gap-2">
                                            <svg class="w-6 h-6 {{ $message->direction === 'outgoing' ? 'text-white' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-sm">{{ $message->content ?? 'Video' }}</span>
                                        </div>
                                    @else
                                        <p class="text-sm">{{ $message->content ?? '[Mensaje]' }}</p>
                                    @endif

                                    <!-- Timestamp & Status -->
                                    <div class="flex items-center justify-end gap-1 mt-1">
                                        <span class="text-xs {{ $message->direction === 'outgoing' ? 'text-green-100' : 'text-gray-400' }}">
                                            {{ $message->sent_at?->format('H:i') ?? $message->created_at->format('H:i') }}
                                        </span>
                                        @if($message->direction === 'outgoing')
                                            @if($message->status === 'read')
                                                <svg class="w-4 h-4 text-blue-300" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/>
                                                </svg>
                                            @elseif($message->status === 'delivered')
                                                <svg class="w-4 h-4 text-green-100" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/>
                                                </svg>
                                            @elseif($message->status === 'sent')
                                                <svg class="w-4 h-4 text-green-100" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                                                </svg>
                                            @elseif($message->status === 'pending')
                                                <svg class="w-4 h-4 text-green-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            @elseif($message->status === 'failed')
                                                <button wire:click="retryMessage({{ $message->id }})" 
                                                        class="flex items-center gap-1 text-red-300 hover:text-red-100 transition-colors"
                                                        title="Reintentar envío">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Message Input -->
                    <div class="bg-white px-4 py-3 border-t border-gray-200">
                        @if($canSend)
                            <form wire:submit="sendMessage" 
                                  class="flex items-end gap-3"
                                  x-data="{ 
                                      messageText: @entangle('messageText'),
                                      isSending: false,
                                      isEmpty() {
                                          return !this.messageText || this.messageText.trim() === '';
                                      }
                                  }">
                                <div class="flex-1 relative">
                                    <textarea x-model="messageText"
                                              wire:model="messageText"
                                              rows="1"
                                              class="w-full px-4 py-2.5 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none text-sm pr-12"
                                              placeholder="Escribe un mensaje..."
                                              x-on:keydown.enter.prevent="if (!$event.shiftKey && !isEmpty()) { isSending = true; $wire.sendMessage().then(() => { isSending = false; }); }"
                                              x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                                              :disabled="isSending"></textarea>
                                    <!-- Character indicator for long messages -->
                                    <span x-show="messageText && messageText.length > 500" 
                                          class="absolute bottom-2 right-3 text-xs"
                                          :class="messageText.length > 4096 ? 'text-red-500' : 'text-gray-400'">
                                        <span x-text="messageText.length"></span>/4096
                                    </span>
                                </div>
                                <button type="submit"
                                        :disabled="isEmpty() || isSending"
                                        class="p-3 bg-green-500 text-white rounded-full hover:bg-green-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex-shrink-0">
                                    <svg x-show="!isSending" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    <svg x-show="isSending" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </button>
                            </form>
                            <!-- Sending status indicator -->
                            <div wire:loading wire:target="sendMessage" class="mt-2 text-xs text-gray-500 flex items-center gap-1">
                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>Enviando mensaje...</span>
                            </div>
                        @else
                            <div class="text-center py-2 text-gray-500 text-sm">
                                <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                No tienes permiso para enviar mensajes
                            </div>
                        @endif
                    </div>
                @else
                    <!-- No conversation selected -->
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-600 mb-2">Selecciona una conversación</h3>
                            <p class="text-gray-500 text-sm">Elige un contacto de la lista para ver los mensajes</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Contact Info Panel -->
            @if($selectedContactId && $this->selectedContact)
                <div class="w-80 flex-shrink-0 bg-white border-l border-gray-200 overflow-hidden">
                    <livewire:chat.contact-info :contact="$this->selectedContact" :key="'contact-info-'.$selectedContactId" />
                </div>
            @endif
        </div>
    @endif
</div>
