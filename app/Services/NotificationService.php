<?php

namespace App\Services;

use App\Models\ChatNotification;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create a notification for a new incoming message.
     * Creates ONE notification per message (not per user) since messages are shared.
     * Requirements: 5.1, 5.2
     */
    public function createMessageNotification(Message $message): ChatNotification
    {
        $contact = $message->contact;
        $channel = $message->channel;
        
        // Check if notification already exists for this message
        $existing = ChatNotification::where('message_id', $message->id)->first();
        if ($existing) {
            return $existing;
        }
        
        // Create a single notification for this message (user_id = null means global)
        // All users assigned to the channel will see this notification
        $notification = ChatNotification::create([
            'user_id' => null, // Global notification - visible to all channel users
            'contact_id' => $contact->id,
            'channel_id' => $message->channel_id,
            'message_id' => $message->id,
            'type' => 'new_message',
            'title' => $contact->display_name,
            'body' => $this->truncateMessage($message->content, 100),
            'is_read' => false,
        ]);
        
        return $notification;
    }

    /**
     * Get the count of unread notifications for a user.
     * Counts global notifications for channels the user has access to.
     * Requirements: 5.1, 5.4
     */
    public function getUnreadCount(int $userId): int
    {
        $user = User::find($userId);
        if (!$user) {
            return 0;
        }

        // Get channel IDs the user has assigned (even for admin)
        $channelIds = $user->channels()->pluck('channels.id');

        if ($channelIds->isEmpty()) {
            return 0;
        }

        // Count unread global notifications for user's channels
        return ChatNotification::whereIn('channel_id', $channelIds)
            ->where('is_read', false)
            ->count();
    }


    /**
     * Get notifications for a user with optional limit.
     * Returns global notifications for channels the user has assigned.
     * Requirements: 5.2
     */
    public function getUserNotifications(int $userId, int $limit = 20): Collection
    {
        $user = User::find($userId);
        if (!$user) {
            return collect();
        }

        // Get channel IDs the user has assigned (even for admin)
        $channelIds = $user->channels()->pluck('channels.id');

        if ($channelIds->isEmpty()) {
            return collect();
        }

        return ChatNotification::whereIn('channel_id', $channelIds)
            ->with(['contact', 'channel', 'message'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a single notification as read.
     * Requirements: 5.4
     */
    public function markAsRead(int $notificationId): void
    {
        ChatNotification::where('id', $notificationId)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Mark all notifications for a conversation as read for ALL users.
     * When one agent reads a conversation, it should be marked as read for everyone.
     * Requirements: 5.4
     */
    public function markConversationAsRead(int $userId, int $contactId, int $channelId): void
    {
        // Mark notifications as read for ALL users assigned to this channel
        // This ensures that when one agent reads a message, all agents see it as read
        ChatNotification::where('contact_id', $contactId)
            ->where('channel_id', $channelId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get notifications grouped by channel for a user.
     * Returns global notifications for channels the user has assigned.
     * Requirements: 5.5
     */
    public function getNotificationsGroupedByChannel(int $userId, int $limit = 20): Collection
    {
        $user = User::find($userId);
        if (!$user) {
            return collect();
        }

        // Get channel IDs the user has assigned (even for admin)
        $channelIds = $user->channels()->pluck('channels.id');

        if ($channelIds->isEmpty()) {
            return collect();
        }

        $notifications = ChatNotification::whereIn('channel_id', $channelIds)
            ->where('is_read', false)
            ->with(['contact', 'channel'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $notifications->groupBy('channel_id');
    }

    /**
     * Truncate message content for notification preview.
     */
    private function truncateMessage(?string $content, int $length): string
    {
        if ($content === null) {
            return '';
        }

        if (mb_strlen($content) <= $length) {
            return $content;
        }

        return mb_substr($content, 0, $length) . '...';
    }
}
