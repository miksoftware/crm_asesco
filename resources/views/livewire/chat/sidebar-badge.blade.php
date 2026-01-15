<span wire:poll.10s>
    @if($this->unreadCount > 0)
        <span class="absolute -top-2 -right-2 flex items-center justify-center min-w-[16px] h-4 px-1 text-[10px] font-bold text-white bg-red-500 rounded-full animate-pulse">
            {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
        </span>
    @endif
</span>
