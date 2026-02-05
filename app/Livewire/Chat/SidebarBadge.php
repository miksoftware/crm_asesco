<?php

namespace App\Livewire\Chat;

use App\Services\NotificationService;
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

        $notificationService = app(NotificationService::class);
        return $notificationService->getUnreadCount($user->id);
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
