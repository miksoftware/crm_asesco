<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a new message is received via webhook.
 * Requirements: 9.2, 9.3
 */
class NewMessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the message data for broadcasting.
     */
    public function getMessageData(): array
    {
        return [
            'id' => $this->message->id,
            'contact_id' => $this->message->contact_id,
            'channel_id' => $this->message->channel_id,
            'direction' => $this->message->direction,
            'type' => $this->message->type,
            'content' => $this->message->content,
            'sent_at' => $this->message->sent_at?->toIso8601String(),
        ];
    }
}
