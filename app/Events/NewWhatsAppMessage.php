<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de broadcasting para mensajes nuevos de WhatsApp.
 * Se emite instantáneamente (ShouldBroadcastNow) sin pasar por la cola.
 */
class NewWhatsAppMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $messageData;

    public function __construct(
        public Message $message
    ) {
        $this->messageData = [
            'id' => $message->id,
            'contact_id' => $message->contact_id,
            'channel_id' => $message->channel_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'content' => $message->content,
            'media_url' => $message->media_url,
            'media_mime_type' => $message->media_mime_type,
            'sender_name' => $message->sender_name,
            'sender_phone' => $message->sender_phone,
            'status' => $message->status,
            'is_read' => $message->is_read,
            'message_id' => $message->message_id,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'contact_name' => $message->contact?->display_name,
            'contact_phone' => $message->contact?->phone_number,
        ];
    }

    /**
     * Canales de broadcasting:
     * - Canal privado por contacto (conversación específica)
     * - Canal privado por canal de WhatsApp (lista de conversaciones)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.contact.{$this->message->contact_id}"),
            new PrivateChannel("chat.channel.{$this->message->channel_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.message';
    }

    public function broadcastWith(): array
    {
        return $this->messageData;
    }
}
