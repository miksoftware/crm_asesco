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
    
    // Track last known unread count to detect new messages
    public int $lastKnownCount = 0;

    public function mount(): void
    {
        $this->lastKnownCount = $this->unreadCount;
    }

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

    /**
     * Check for new notifications and trigger browser notification.
     * Called by polling from the view.
     */
    public function checkNewNotifications(): void
    {
        // Clear computed cache to get fresh count
        unset($this->unreadCount);
        unset($this->notifications);
        unset($this->notificationsGrouped);
        
        $currentCount = $this->unreadCount;
        
        // If count increased, we have new messages
        if ($currentCount > $this->lastKnownCount) {
            // Get the latest unread notification to show in browser
            $latestNotification = \App\Models\ChatNotification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->with(['contact', 'channel'])
                ->orderByDesc('created_at')
                ->first();
            
            if ($latestNotification) {
                $contactName = $latestNotification->contact?->display_name ?? 'Contacto';
                $channelName = $latestNotification->channel?->name ?? 'Canal';
                $messagePreview = $latestNotification->body ?? 'Nuevo mensaje';
                
                // Dispatch browser notification event
                $this->dispatch('browser-notification', 
                    title: "{$contactName} - {$channelName}",
                    body: $messagePreview,
                    channelId: $latestNotification->channel_id,
                    contactId: $latestNotification->contact_id
                );
            }
        }
        
        $this->lastKnownCount = $currentCount;
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

        // Obtener los canales asignados al usuario
        $channelIds = $user->channels()->pluck('channels.id');

        if ($channelIds->isNotEmpty()) {
            // Marcar todas las notificaciones de esos canales como leídas (incluyendo las globales user_id = null)
            \App\Models\ChatNotification::whereIn('channel_id', $channelIds)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
            
            // También marcar los mensajes reales subyacentes como leídos para limpiar los badges de los canales
            \App\Models\Message::whereIn('channel_id', $channelIds)
                ->where('direction', 'incoming')
                ->where('is_read', false)
                ->update([
                    'is_read' => true
                ]);
        }

        // Clear computed caches
        unset($this->unreadCount);
        unset($this->notificationsGrouped);
        unset($this->notifications);
        
        $this->lastKnownCount = 0;

        $this->dispatch('toast', type: 'success', message: 'Todas las notificaciones marcadas como leídas');
        $this->dispatch('notifications-updated');
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
        $this->lastKnownCount = $this->unreadCount;
    }

    public function render()
    {
        return view('livewire.chat.notification-badge');
    }
}
