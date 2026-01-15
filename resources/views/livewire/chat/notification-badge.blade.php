<div class="relative" 
     x-data="{ open: @entangle('showDropdown') }"
     wire:poll.2s="$refresh">
    <!-- Notification Bell Button -->
    <button 
        @click="open = !open"
        class="relative p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-primary-500"
        aria-label="Notificaciones"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        
        <!-- Unread Count Badge -->
        @if($this->unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-bold text-white bg-red-500 rounded-full">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown Panel -->
    <div 
        x-show="open"
        x-cloak
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
        class="absolute right-0 mt-2 w-96 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden"
    >
        <!-- Header -->
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Notificaciones</h3>
            @if($this->unreadCount > 0)
                <button 
                    wire:click="markAllAsRead"
                    class="text-xs text-primary-600 hover:text-primary-700 font-medium"
                >
                    Marcar todas como leídas
                </button>
            @endif
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
            @if($this->notificationsGrouped->isEmpty())
                <div class="px-4 py-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-sm text-gray-500">No tienes notificaciones nuevas</p>
                </div>
            @else
                @foreach($this->notificationsGrouped as $channelId => $channelNotifications)
                    @php
                        $channel = $channelNotifications->first()?->channel;
                    @endphp
                    
                    <!-- Channel Group Header -->
                    <div class="px-4 py-2 bg-gray-100 border-b border-gray-200">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            <span class="text-xs font-semibold text-gray-600">{{ $channel?->name ?? 'Canal' }}</span>
                            <span class="text-xs text-gray-400">({{ $channelNotifications->count() }})</span>
                        </div>
                    </div>

                    <!-- Channel Notifications -->
                    @foreach($channelNotifications as $notification)
                        <button
                            wire:click="navigateToConversation({{ $notification->contact_id }}, {{ $notification->channel_id }})"
                            class="w-full px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0 text-left"
                        >
                            <div class="flex items-start space-x-3">
                                <!-- Contact Avatar -->
                                <div class="flex-shrink-0">
                                    @if($notification->contact?->profile_picture)
                                        <img 
                                            src="{{ $notification->contact->profile_picture }}" 
                                            alt="{{ $notification->contact->display_name }}"
                                            class="w-10 h-10 rounded-full object-cover"
                                        >
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center">
                                            <span class="text-white text-sm font-semibold">
                                                {{ strtoupper(substr($notification->title ?? 'U', 0, 1)) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Notification Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ $notification->title }}
                                        </p>
                                        <span class="text-xs text-gray-400 flex-shrink-0 ml-2">
                                            {{ $notification->created_at->diffForHumans(short: true) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 truncate mt-0.5">
                                        {{ $notification->body }}
                                    </p>
                                </div>

                                <!-- Unread Indicator -->
                                @if(!$notification->is_read)
                                    <div class="flex-shrink-0">
                                        <span class="w-2 h-2 bg-primary-500 rounded-full block"></span>
                                    </div>
                                @endif
                            </div>
                        </button>
                    @endforeach
                @endforeach
            @endif
        </div>

        <!-- Footer -->
        @if($this->unreadCount > 0)
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-center">
                <a 
                    href="{{ route('chat.index') }}"
                    class="text-sm text-primary-600 hover:text-primary-700 font-medium"
                    wire:navigate
                >
                    Ver todas las conversaciones
                </a>
            </div>
        @endif
    </div>
</div>
