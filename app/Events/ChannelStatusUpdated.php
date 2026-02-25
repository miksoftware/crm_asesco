<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de broadcasting para cambios de estado de conexión de canales.
 */
class ChannelStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $channelId,
        public string $status,
        public string $instanceName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.channel.{$this->channelId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'channel.status';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->channelId,
            'status' => $this->status,
            'instance_name' => $this->instanceName,
        ];
    }
}
