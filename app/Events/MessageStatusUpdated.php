<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de broadcasting para actualizaciones de estado de mensajes.
 * Estados: pending → sent → delivered → read
 */
class MessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $messageId,
        public string $externalMessageId,
        public string $status,
        public int $contactId,
        public int $channelId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.contact.{$this->contactId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.status';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'external_message_id' => $this->externalMessageId,
            'status' => $this->status,
            'contact_id' => $this->contactId,
            'channel_id' => $this->channelId,
        ];
    }
}
