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
     * Requirements: 5.1, 5.2
     */
    public function createMessageNotification(Message $message): ChatNotification
    {
        // Get all users assigned to this channel
        $users = User::whereHas('channels', function ($query) use ($message) {
            $query->where('channels.id', $message->channel_id);
        })->get();

        $contact = $message->contact;
        $channel = $message->channel;
        
        // Create notification for each user assigned to the channel
        $notification = null;
        
        foreach ($users as $user) {
            $notification = ChatNotification::create([
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'channel_id' => $message->channel_id,
                'message_id' => $message->id,
                'type' => 'new_message',
                'title' => $contact->display_name,
                'body' => $this->truncateMessage($message->content, 100),
                'is_read' => false,
            ]);
        }
        
        // Return the last created notification (or create a dummy one if no users)
        return $notification ?? ChatNotification::make([
            'contact_id' => $contact->id,
            'channel_id' => $message->channel_id,
            'message_id' => $message->id,
            'type' => 'new_message',
            'title' => $contact->display_name,
            'body' => $this->truncateMessage($message->content, 100),
            'is_read' => false,
        ]);
    }

    /**
     * Get the count of unread notifications for a user.
     * Requirements: 5.1, 5.4
     */
    public function getUnreadCount(int $userId): int
    {
        return ChatNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }


    /**
     * Get notifications for a user with optional limit.
     * Requirements: 5.2
     */
    public function getUserNotifications(int $userId, int $limit = 20): Collection
    {
        return ChatNotification::where('user_id', $userId)
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
     * Mark all notifications for a conversation as read.
     * Requirements: 5.4
     */
    public function markConversationAsRead(int $userId, int $contactId, int $channelId): void
    {
        ChatNotification::where('user_id', $userId)
            ->where('contact_id', $contactId)
            ->where('channel_id', $channelId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get notifications grouped by channel for a user.
     * Requirements: 5.5
     */
    public function getNotificationsGroupedByChannel(int $userId, int $limit = 20): Collection
    {
        $notifications = ChatNotification::where('user_id', $userId)
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
