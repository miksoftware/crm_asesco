<?php

namespace App\Livewire\Chat;

use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * NotificationBadge Component
 * 
 * Displays a notification badge in the header with unread count
 * and a dropdown list of notifications grouped by channel.
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.5
 */
class NotificationBadge extends Component
{
    public bool $showDropdown = false;

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

    #[Computed]
    public function notificationsGrouped(): Collection
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        $notificationService = app(NotificationService::class);
        return $notificationService->getNotificationsGroupedByChannel($user->id, 20);
    }

    #[Computed]
    public function notifications(): Collection
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        $notificationService = app(NotificationService::class);
        return $notificationService->getUserNotifications($user->id, 20);
    }

    public function toggleDropdown(): void
    {
        $this->showDropdown = !$this->showDropdown;
    }

    public function closeDropdown(): void
    {
        $this->showDropdown = false;
    }

    /**
     * Navigate to the conversation when clicking a notification.
     * Requirements: 5.3
     */
    public function navigateToConversation(int $contactId, int $channelId): void
    {
        $this->showDropdown = false;
        
        // Mark notifications for this conversation as read
        $notificationService = app(NotificationService::class);
        $notificationService->markConversationAsRead(
            auth()->id(),
            $contactId,
            $channelId
        );

        // Clear computed caches
        unset($this->unreadCount);
        unset($this->notificationsGrouped);
        unset($this->notifications);

        // Navigate to chat with the selected conversation
        $this->redirect(route('chat.index', [
            'selectedChannelId' => $channelId,
            'selectedContactId' => $contactId,
        ]));
    }

    /**
     * Mark a single notification as read.
     * Requirements: 5.4
     */
    public function markAsRead(int $notificationId): void
    {
        $notificationService = app(NotificationService::class);
        $notificationService->markAsRead($notificationId);

        // Clear computed caches
        unset($this->unreadCount);
        unset($this->notificationsGrouped);
        unset($this->notifications);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        \App\Models\ChatNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        // Clear computed caches
        unset($this->unreadCount);
        unset($this->notificationsGrouped);
        unset($this->notifications);

        $this->dispatch('toast', type: 'success', message: 'Todas las notificaciones marcadas como leídas');
    }

    /**
     * Handle new message event to refresh notifications.
     * Requirements: 5.1
     */
    #[On('new-message')]
    public function handleNewMessage(): void
    {
        // Clear computed caches to refresh notification count
        unset($this->unreadCount);
        unset($this->notificationsGrouped);
        unset($this->notifications);
    }

    /**
     * Handle notification read event.
     */
    #[On('notifications-updated')]
    public function handleNotificationsUpdated(): void
    {
        unset($this->unreadCount);
        unset($this->notificationsGrouped);
        unset($this->notifications);
    }

    public function render()
    {
        return view('livewire.chat.notification-badge');
    }
}
