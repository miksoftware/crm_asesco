<div class="h-[calc(100vh-8rem)] flex flex-col" x-data="{ showContactInfo: true }">
    @if($this->channels->isEmpty())
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
        <!-- Channel Tabs - Full Width Top Bar -->
        <div class="bg-white border-b-2 border-gray-300 flex-shrink-0">
            <div class="flex">
                @foreach($this->channels as $channel)
                    <button wire:click="selectChannel({{ $channel->id }})"
                            wire:key="channel-tab-{{ $channel->id }}"
                            class="flex-1 px-4 py-3 text-sm font-medium border-b-2 -mb-[2px] transition-colors whitespace-nowrap
                                   {{ $selectedChannelId === $channel->id 
                                      ? 'border-green-500 text-green-600 bg-green-50' 
                                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                        <span class="flex items-center justify-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            {{ $channel->name }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex-1 flex overflow-hidden">
            <!-- Left Column -->
            <div class="w-80 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col">
                <!-- Search & Filter -->
                <div class="p-3 border-b border-gray-200 space-y-2">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input wire:model.live.debounce.300ms="search" type="text" 
                               class="block w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Buscar contacto...">
                    </div>

                    <!-- Label Filter & Sync Button -->
                    <div class="flex items-center gap-2">
                        <select wire:model.live="labelFilter" 
                                class="flex-1 px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-green-500">
                            <option value="">Todas las etiquetas</option>
                            @foreach($this->availableLabels as $label)
                                <option value="{{ $label->id }}">{{ $label->name }}</option>
                            @endforeach
                        </select>
                        @if($labelFilter)
                            <button wire:click="clearLabelFilter" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg" title="Limpiar filtro">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>

                    <!-- Date Filter -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input type="date" 
                                   wire:model.live="dateFilter"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-green-500"
                                   title="Filtrar por fecha de último mensaje">
                        </div>
                        @if($dateFilter)
                            <button wire:click="clearDateFilter" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg" title="Limpiar filtro de fecha">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                        <!-- Sync Button -->
                        <button wire:click="syncMessages" 
                                wire:loading.attr="disabled"
                                wire:target="syncMessages"
                                class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors disabled:opacity-50"
                                title="Sincronizar mensajes">
                            <svg wire:loading.remove wire:target="syncMessages" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <svg wire:loading wire:target="syncMessages" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                        <!-- New Chat Button -->
                        @if($canSend)
                            <button wire:click="openNewChatModal" 
                                    class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                    title="Nueva conversación">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Conversations List -->
                <div class="flex-1 overflow-y-auto">
                    @forelse($this->conversations as $contact)
                        <div wire:key="contact-{{ $contact->id }}"
                             class="group relative flex items-center gap-3 p-3 cursor-pointer border-b border-gray-100 hover:bg-gray-50 transition-colors
                                    {{ $selectedContactId === $contact->id ? 'bg-green-50 border-l-4 border-l-green-500' : '' }}
                                    {{ $contact->unread_count > 0 ? 'bg-green-50/50' : '' }}">
                            <!-- Mark as Unread Button (appears on hover) -->
                            @if($contact->unread_count == 0)
                                <button wire:click.stop="markAsUnread({{ $contact->id }})"
                                        class="absolute right-2 top-2 p-1 rounded-full bg-white shadow-sm border border-gray-200 text-gray-400 hover:text-green-600 hover:border-green-300 opacity-0 group-hover:opacity-100 transition-all z-10"
                                        title="Marcar como no leído">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="10" cy="10" r="6"/>
                                    </svg>
                                </button>
                            @endif
                            <div wire:click="selectConversation({{ $contact->id }})" class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    @if($contact->profile_picture)
                                        <img src="{{ $contact->profile_picture }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-lg font-semibold text-gray-500">{{ strtoupper(substr($contact->display_name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium text-gray-900 truncate {{ $contact->unread_count > 0 ? 'font-bold' : '' }}">
                                            {{ $contact->display_name }}
                                        </h4>
                                        @if($contact->last_message)
                                            <span class="text-xs {{ $contact->unread_count > 0 ? 'text-green-600 font-semibold' : 'text-gray-400' }} flex-shrink-0 ml-2">
                                                {{ $contact->last_message->sent_at?->format('H:i') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between mt-0.5">
                                        <p class="text-sm text-gray-500 truncate {{ $contact->unread_count > 0 ? 'font-medium text-gray-700' : '' }}">
                                            @if($contact->last_message)
                                                @if($contact->last_message->direction === 'outgoing')
                                                    @if($contact->last_message->status === 'read')
                                                        <svg class="w-4 h-4 inline text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>
                                                    @elseif($contact->last_message->status === 'delivered')
                                                        <svg class="w-4 h-4 inline text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>
                                                    @else
                                                        <svg class="w-4 h-4 inline text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                                    @endif
                                                @endif
                                            {{ Str::limit($contact->last_message->content ?? '[Media]', 25) }}
                                        @else
                                            Sin mensajes
                                        @endif
                                    </p>
                                    @if($contact->unread_count > 0)
                                        <span class="ml-2 px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">{{ $contact->unread_count }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <p class="text-sm mb-3">No hay conversaciones</p>
                            @if($selectedChannelId)
                                <button wire:click="syncMessages" 
                                        wire:loading.attr="disabled"
                                        wire:target="syncMessages"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-500 text-white text-sm font-medium rounded-lg hover:bg-green-600 transition-colors disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="syncMessages" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <svg wire:loading wire:target="syncMessages" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="syncMessages">Sincronizar mensajes</span>
                                    <span wire:loading wire:target="syncMessages">Sincronizando...</span>
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Center Column: Messages Area -->
            <div class="flex-1 flex flex-col bg-[#efeae2]" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23d5d0c8\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');">
                @if($selectedContactId && $this->selectedContact)
                    <!-- Chat Header -->
                    <div class="bg-[#f0f2f5] px-4 py-2 border-b border-gray-200 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden">
                            @if($this->selectedContact->profile_picture)
                                <img src="{{ $this->selectedContact->profile_picture }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="text-lg font-semibold text-gray-500">{{ strtoupper(substr($this->selectedContact->display_name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">{{ $this->selectedContact->display_name }}</h3>
                            <p class="text-xs text-gray-500">{{ $this->selectedContact->phone_number }}</p>
                        </div>
                        <!-- Transfer Button -->
                        <button wire:click="openTransferModal" class="p-2 text-gray-500 hover:bg-gray-200 rounded-full transition-colors" title="Transferir chat">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </button>
                        <!-- Toggle Contact Info Button -->
                        <button @click="showContactInfo = !showContactInfo" 
                                class="p-2 text-gray-500 hover:bg-gray-200 rounded-full transition-colors" 
                                :title="showContactInfo ? 'Ocultar información' : 'Mostrar información'">
                            <svg x-show="!showContactInfo" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <svg x-show="showContactInfo" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Messages Container -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-1" id="messages-container"
                         x-data="{ 
                             isLoadingMore: false, lastScrollHeight: 0, userScrolledUp: false, lastMessageCount: @js(count($this->messages)),
                             scrollToBottom() { this.$nextTick(() => { this.$el.scrollTop = this.$el.scrollHeight; this.userScrolledUp = false; }); },
                             preserveScrollPosition() { this.$nextTick(() => { const diff = this.$el.scrollHeight - this.lastScrollHeight; this.$el.scrollTop = diff; }); },
                             handleScroll() {
                                 this.userScrolledUp = (this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight) > 100;
                                 if (this.$el.scrollTop < 100 && !this.isLoadingMore && @js($hasMoreMessages)) {
                                     this.isLoadingMore = true; this.lastScrollHeight = this.$el.scrollHeight;
                                     $wire.loadMoreMessages().then(() => { this.preserveScrollPosition(); this.isLoadingMore = false; });
                                 }
                             },
                             checkNewMessages() { const c = @js(count($this->messages)); if (c > this.lastMessageCount && !this.userScrolledUp) this.scrollToBottom(); this.lastMessageCount = c; }
                         }"
                         x-init="scrollToBottom()" @scroll.debounce.100ms="handleScroll()" x-effect="checkNewMessages()" wire:poll.visible.5s>
                        
                        @if($hasMoreMessages)
                            <div class="text-center mb-2">
                                <button wire:click="loadMoreMessages" class="px-4 py-1.5 text-xs text-gray-600 bg-white rounded-full shadow hover:bg-gray-50">
                                    Cargar anteriores
                                </button>
                            </div>
                        @endif

                        <!-- Messages -->
                        @foreach($this->messages as $message)
                            <div wire:key="message-{{ $message->id }}" class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }} mb-1">
                                <div class="max-w-[65%] {{ $message->direction === 'outgoing' ? 'bg-[#d9fdd3]' : 'bg-white' }} rounded-lg px-3 py-1.5 shadow-sm relative min-w-[80px]"
                                     style="{{ $message->direction === 'outgoing' ? 'border-top-right-radius: 0;' : 'border-top-left-radius: 0;' }}">
                                    @if($message->type === 'text')
                                        <p class="text-sm text-gray-800 whitespace-pre-wrap break-words pr-14">{{ $message->content ?: '[Mensaje vacío]' }}</p>
                                    @elseif($message->type === 'image')
                                        @if($message->media_url)
                                            <a href="{{ $message->media_url }}" target="_blank" class="block">
                                                <img src="{{ $message->media_url }}" alt="Imagen" class="max-w-full max-h-64 rounded-lg cursor-pointer" loading="lazy">
                                            </a>
                                        @else
                                            <button wire:click="loadMessageMedia({{ $message->id }})" 
                                                    wire:loading.attr="disabled"
                                                    wire:target="loadMessageMedia({{ $message->id }})"
                                                    class="flex items-center gap-2 text-gray-500 py-2 px-3 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                                <svg wire:loading.remove wire:target="loadMessageMedia({{ $message->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <svg wire:loading wire:target="loadMessageMedia({{ $message->id }})" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                <span class="text-sm">{{ $message->content ?: 'Cargar imagen' }}</span>
                                            </button>
                                        @endif
                                        @if($message->content && $message->content !== '[Media]' && $message->content !== '[Imagen]' && $message->media_url)
                                            <p class="text-sm text-gray-800 mt-1 pr-14">{{ $message->content }}</p>
                                        @endif
                                    @elseif($message->type === 'audio')
                                        @if($message->media_url)
                                            <audio controls class="max-w-full"><source src="{{ $message->media_url }}" type="{{ $message->media_mime_type ?? 'audio/ogg' }}"></audio>
                                        @else
                                            <button wire:click="loadMessageMedia({{ $message->id }})" 
                                                    wire:loading.attr="disabled"
                                                    wire:target="loadMessageMedia({{ $message->id }})"
                                                    class="flex items-center gap-2 text-gray-500 py-2 px-3 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                                <svg wire:loading.remove wire:target="loadMessageMedia({{ $message->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                                <svg wire:loading wire:target="loadMessageMedia({{ $message->id }})" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                <span class="text-sm">Cargar audio</span>
                                            </button>
                                        @endif
                                    @elseif($message->type === 'document')
                                        @if($message->media_url)
                                            <div class="flex items-center gap-2 p-2 bg-gray-100 rounded-lg hover:bg-gray-200 cursor-pointer"
                                                 onclick="window.open('{{ $message->media_url }}', '_blank')">
                                                <svg class="w-8 h-8 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span class="text-sm text-gray-700">{{ $message->content ?: 'Documento' }}</span>
                                            </div>
                                        @else
                                            <button wire:click="loadMessageMedia({{ $message->id }})" 
                                                    wire:loading.attr="disabled"
                                                    wire:target="loadMessageMedia({{ $message->id }})"
                                                    class="flex items-center gap-2 p-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                                <svg wire:loading.remove wire:target="loadMessageMedia({{ $message->id }})" class="w-8 h-8 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <svg wire:loading wire:target="loadMessageMedia({{ $message->id }})" class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                <span class="text-sm text-gray-700">{{ $message->content ?: 'Cargar documento' }}</span>
                                            </button>
                                        @endif
                                    @elseif($message->type === 'video')
                                        @if($message->media_url)
                                            <video controls class="max-w-full max-h-64 rounded-lg"><source src="{{ $message->media_url }}" type="{{ $message->media_mime_type ?? 'video/mp4' }}"></video>
                                        @else
                                            <button wire:click="loadMessageMedia({{ $message->id }})" 
                                                    wire:loading.attr="disabled"
                                                    wire:target="loadMessageMedia({{ $message->id }})"
                                                    class="flex items-center gap-2 text-gray-500 py-2 px-3 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                                <svg wire:loading.remove wire:target="loadMessageMedia({{ $message->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                <svg wire:loading wire:target="loadMessageMedia({{ $message->id }})" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                <span class="text-sm">Cargar video</span>
                                            </button>
                                        @endif
                                    @elseif($message->type === 'contact')
                                        <div class="flex items-center gap-2 p-2 bg-gray-100 rounded-lg">
                                            <svg class="w-8 h-8 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            <span class="text-sm text-gray-700">{{ $message->content ?: 'Contacto compartido' }}</span>
                                        </div>
                                    @elseif($message->type === 'location')
                                        <div class="flex items-center gap-2 p-2 bg-gray-100 rounded-lg">
                                            <svg class="w-8 h-8 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span class="text-sm text-gray-700">{{ $message->content ?: 'Ubicación compartida' }}</span>
                                        </div>
                                    @elseif($message->type === 'sticker')
                                        @if($message->media_url)
                                            <a href="{{ $message->media_url }}" target="_blank" class="block">
                                                <img src="{{ $message->media_url }}" alt="Sticker" class="max-w-[150px] max-h-[150px] rounded-lg cursor-pointer" loading="lazy">
                                            </a>
                                        @else
                                            <button wire:click="loadMessageMedia({{ $message->id }})" 
                                                    wire:loading.attr="disabled"
                                                    wire:target="loadMessageMedia({{ $message->id }})"
                                                    class="flex items-center gap-2 text-gray-500 py-2 px-3 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                                <span wire:loading.remove wire:target="loadMessageMedia({{ $message->id }})" class="text-2xl">🎭</span>
                                                <svg wire:loading wire:target="loadMessageMedia({{ $message->id }})" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                <span class="text-sm italic">Cargar sticker</span>
                                            </button>
                                        @endif
                                    @elseif($message->type === 'deleted')
                                        <div class="flex items-center gap-2 text-gray-400 py-1 italic">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            <span class="text-sm">Mensaje eliminado</span>
                                        </div>
                                    @else
                                        {{-- Fallback for unknown types --}}
                                        <p class="text-sm text-gray-600 italic pr-14">{{ $message->content ?: '[' . ucfirst($message->type ?? 'Mensaje') . ']' }}</p>
                                    @endif
                                    <!-- Time & Status -->
                                    <div class="flex items-center justify-end gap-1 {{ in_array($message->type, ['text']) && $message->content ? 'absolute bottom-1 right-2' : 'mt-1' }}">
                                        <span class="text-[10px] text-gray-500">{{ $message->sent_at?->format('H:i') }}</span>
                                        @if($message->direction === 'outgoing')
                                            @if($message->status === 'read')
                                                <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>
                                            @elseif($message->status === 'delivered')
                                                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>
                                            @elseif($message->status === 'sent')
                                                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                            @elseif($message->status === 'pending')
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @elseif($message->status === 'failed')
                                                <button wire:click="retryMessage({{ $message->id }})" class="text-red-500 hover:text-red-700" title="Reintentar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Message Input Area -->
                    <div class="bg-[#f0f2f5] px-4 py-3 border-t border-gray-200">
                        @if($canSend)
                            <!-- Media Preview -->
                            @if($mediaPreview)
                                <div class="mb-3 p-3 bg-white rounded-lg border border-gray-200 flex items-center gap-3">
                                    @if($mediaType === 'image')
                                        <img src="{{ $mediaPreview }}" alt="Preview" class="w-16 h-16 object-cover rounded">
                                    @elseif($mediaType === 'video')
                                        <video src="{{ $mediaPreview }}" class="w-16 h-16 object-cover rounded"></video>
                                    @else
                                        <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                                            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                    @endif
                                    <span class="flex-1 text-sm text-gray-600 truncate">{{ $mediaType === 'image' || $mediaType === 'video' ? 'Archivo multimedia' : $mediaPreview }}</span>
                                    <button wire:click="clearMedia" class="p-1 text-gray-400 hover:text-red-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @endif

                            <div class="flex items-end gap-2"
                                 x-data="{
                                     isRecording: false,
                                     mediaRecorder: null,
                                     audioChunks: [],
                                     recordingTime: 0,
                                     recordingInterval: null,
                                     messageText: @entangle('messageText'),
                                     
                                     async startRecording() {
                                         try {
                                             const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                             this.mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm;codecs=opus' });
                                             this.audioChunks = [];
                                             
                                             this.mediaRecorder.ondataavailable = (e) => {
                                                 if (e.data.size > 0) this.audioChunks.push(e.data);
                                             };
                                             
                                             this.mediaRecorder.onstop = async () => {
                                                 stream.getTracks().forEach(track => track.stop());
                                                 const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                                                 const reader = new FileReader();
                                                 reader.onloadend = () => {
                                                     $wire.sendVoiceMessage(reader.result);
                                                 };
                                                 reader.readAsDataURL(audioBlob);
                                             };
                                             
                                             this.mediaRecorder.start();
                                             this.isRecording = true;
                                             this.recordingTime = 0;
                                             this.recordingInterval = setInterval(() => { this.recordingTime++; }, 1000);
                                         } catch (err) {
                                             console.error('Error accessing microphone:', err);
                                             alert('No se pudo acceder al micrófono. Verifica los permisos.');
                                         }
                                     },
                                     
                                     stopRecording() {
                                         if (this.mediaRecorder && this.isRecording) {
                                             this.mediaRecorder.stop();
                                             this.isRecording = false;
                                             clearInterval(this.recordingInterval);
                                         }
                                     },
                                     
                                     cancelRecording() {
                                         if (this.mediaRecorder && this.isRecording) {
                                             this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
                                             this.isRecording = false;
                                             clearInterval(this.recordingInterval);
                                             this.audioChunks = [];
                                         }
                                     },
                                     
                                     formatTime(seconds) {
                                         const m = Math.floor(seconds / 60);
                                         const s = seconds % 60;
                                         return m + ':' + (s < 10 ? '0' : '') + s;
                                     }
                                 }">
                                
                                <!-- Recording UI -->
                                <template x-if="isRecording">
                                    <div class="flex-1 flex items-center gap-3 bg-white rounded-3xl px-4 py-2">
                                        <button type="button" @click="cancelRecording()" class="p-2 text-red-500 hover:bg-red-50 rounded-full">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <div class="flex-1 flex items-center gap-2">
                                            <span class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                                            <span class="text-sm text-gray-600" x-text="formatTime(recordingTime)"></span>
                                            <div class="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-full bg-red-500 animate-pulse" style="width: 100%"></div>
                                            </div>
                                        </div>
                                        <button type="button" @click="stopRecording()" class="p-2.5 bg-green-500 text-white rounded-full hover:bg-green-600">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                        </button>
                                    </div>
                                </template>

                                <!-- Normal Input UI -->
                                <template x-if="!isRecording">
                                    <div class="flex-1 flex items-end gap-2">
                                        <!-- Attachment Button -->
                                        <div class="relative" x-data="{ open: false }">
                                            <button type="button" @click="open = !open" class="p-2.5 text-gray-500 hover:bg-gray-200 rounded-full transition-colors">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" x-transition class="absolute bottom-full left-0 mb-2 bg-white rounded-xl shadow-lg border border-gray-200 py-2 min-w-[160px] z-10">
                                                <label class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    <span class="text-sm">Imagen</span>
                                                    <input type="file" wire:model="mediaFile" accept="image/*" class="hidden" @change="open = false">
                                                </label>
                                                <label class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                    <span class="text-sm">Video</span>
                                                    <input type="file" wire:model="mediaFile" accept="video/*" class="hidden" @change="open = false">
                                                </label>
                                                <label class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    <span class="text-sm">Documento</span>
                                                    <input type="file" wire:model="mediaFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" class="hidden" @change="open = false">
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Text Input -->
                                        <form wire:submit="sendMessage" class="flex-1 flex items-end gap-2">
                                            <div class="flex-1">
                                                <textarea wire:model="messageText" rows="1"
                                                          class="w-full px-4 py-2.5 bg-white border-0 rounded-3xl focus:ring-0 resize-none text-sm"
                                                          placeholder="Escribe un mensaje..."
                                                          x-on:keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage(); }"
                                                          x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 100) + 'px'"></textarea>
                                            </div>

                                            <!-- Send or Mic Button -->
                                            <template x-if="messageText && messageText.trim().length > 0">
                                                <button type="submit" class="p-2.5 bg-green-500 text-white rounded-full hover:bg-green-600 transition-colors">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                                </button>
                                            </template>
                                        </form>
                                        <template x-if="!messageText || messageText.trim().length === 0">
                                            <button type="button" @click="startRecording()" class="p-2.5 text-gray-500 hover:bg-gray-200 rounded-full transition-colors">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        @else
                            <div class="text-center py-2 text-gray-500 text-sm">
                                <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                No tienes permiso para enviar mensajes
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            @if(!$this->hasConversations && $selectedChannelId)
                                <!-- No conversations - show sync button -->
                                <div class="w-24 h-24 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-600 mb-2">Sin conversaciones</h3>
                                <p class="text-gray-500 text-sm mb-4">Este canal no tiene mensajes. Sincroniza los mensajes existentes desde WhatsApp.</p>
                                <button wire:click="syncMessages" 
                                        wire:loading.attr="disabled"
                                        wire:target="syncMessages"
                                        class="inline-flex items-center gap-2 px-6 py-3 bg-green-500 text-white font-medium rounded-xl hover:bg-green-600 transition-colors disabled:opacity-50 shadow-lg">
                                    <svg wire:loading.remove wire:target="syncMessages" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <svg wire:loading wire:target="syncMessages" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="syncMessages">Sincronizar mensajes</span>
                                    <span wire:loading wire:target="syncMessages">Sincronizando...</span>
                                </button>
                            @else
                                <!-- Has conversations - show select message -->
                                <div class="w-24 h-24 rounded-full bg-[#d9fdd3] flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-600 mb-2">Selecciona una conversación</h3>
                                <p class="text-gray-500 text-sm">Elige un contacto de la lista para ver los mensajes</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Contact Info Panel -->
            @if($selectedContactId && $this->selectedContact)
                <div x-show="showContactInfo" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-x-4"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-x-0"
                     x-transition:leave-end="opacity-0 translate-x-4"
                     class="w-80 flex-shrink-0 bg-white border-l border-gray-200 overflow-y-auto">
                    <livewire:chat.contact-info :contact="$this->selectedContact" :channel-id="$selectedChannelId" :key="'contact-info-'.$selectedContactId" />
                </div>
            @endif
        </div>
    @endif

    <!-- Transfer Modal -->
    @teleport('body')
        <div x-data="{ show: @entangle('showTransferModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" x-show="show" x-transition wire:click="closeTransferModal"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6" x-show="show" x-transition>
                    <div class="text-center mb-4">
                        <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Transferir Chat</h3>
                        <p class="text-sm text-gray-500">{{ $this->selectedContact?->display_name }}</p>
                    </div>
                    <form wire:submit="transferChat" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Transferir a usuario <span class="text-red-500">*</span></label>
                            <select wire:model="transferToUserId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleccionar usuario...</option>
                                @foreach($this->availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('transferToUserId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Canal destino</label>
                            <select wire:model="transferToChannelId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                                @foreach($this->allChannels as $channel)
                                    <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nota interna (solo visible para agentes)</label>
                            <textarea wire:model="transferNote" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm resize-none focus:ring-2 focus:ring-blue-500" placeholder="Motivo de la transferencia..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" wire:click="closeTransferModal" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium">Cancelar</button>
                            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-500 text-white rounded-xl hover:bg-blue-600 font-medium">Transferir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endteleport

    <!-- New Label Modal -->
    @teleport('body')
        <div x-data="{ show: @entangle('showNewLabelModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" x-show="show" x-transition wire:click="closeNewLabelModal"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6" x-show="show" x-transition>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Nueva Etiqueta</h3>
                    <form wire:submit="createLabel" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" wire:model="newLabelName" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-green-500" placeholder="Ej: Urgente">
                            @error('newLabelName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" wire:model="newLabelColor" class="w-12 h-10 rounded border-0 cursor-pointer">
                                <input type="text" wire:model="newLabelColor" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl text-sm" placeholder="#6b7280">
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" wire:click="closeNewLabelModal" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium">Cancelar</button>
                            <button type="submit" class="flex-1 px-4 py-2.5 bg-green-500 text-white rounded-xl hover:bg-green-600 font-medium">Crear</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endteleport

    <!-- New Chat Modal -->
    @teleport('body')
        <div x-data="{ show: @entangle('showNewChatModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" x-show="show" x-transition wire:click="closeNewChatModal"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6" x-show="show" x-transition>
                    <div class="text-center mb-4">
                        <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Nueva Conversación</h3>
                        <p class="text-sm text-gray-500">Inicia un chat con un nuevo número de WhatsApp</p>
                    </div>
                    <form wire:submit="startNewChat" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número de WhatsApp <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <input type="text" wire:model="newChatNumber" 
                                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                       placeholder="Ej: 573001234567"
                                       inputmode="numeric">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Incluye el código de país sin el signo +</p>
                            @error('newChatNumber') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje inicial <span class="text-red-500">*</span></label>
                            <textarea wire:model="newChatMessage" rows="3" 
                                      class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm resize-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                      placeholder="Escribe tu mensaje..."></textarea>
                            @error('newChatMessage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" wire:click="closeNewChatModal" 
                                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium transition-colors"
                                    wire:loading.attr="disabled">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-2.5 bg-green-500 text-white rounded-xl hover:bg-green-600 font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                    wire:loading.attr="disabled"
                                    wire:target="startNewChat"
                                    @if($isCheckingNumber) disabled @endif>
                                <svg wire:loading wire:target="startNewChat" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="startNewChat">Iniciar Chat</span>
                                <span wire:loading wire:target="startNewChat">Verificando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endteleport
</div>
