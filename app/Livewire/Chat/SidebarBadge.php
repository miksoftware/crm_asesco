<?php

namespace App\Livewire\Chat;

use App\Models\ChatNotification;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Small component for sidebar unread badge with polling.
 */
class SidebarBadge extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        $user = auth()->user();
        if (!$user) {
            return 0;
        }

        return ChatNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    #[On('notifications-updated')]
    public function handleNotificationsUpdated(): void
    {
        unset($this->unreadCount);
    }

    public function render()
    {
        return view('livewire.chat.sidebar-badge');
    }
}
