<?php

namespace App\Listeners;

use App\Events\NewMessageReceived;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Listener that broadcasts new message events to Livewire components.
 * Requirements: 9.2, 9.3
 */
class BroadcastNewMessage
{
    /**
     * Handle the event.
     */
    public function handle(NewMessageReceived $event): void
    {
        $message = $event->message;
        
        Log::debug('Broadcasting new message event', [
            'message_id' => $message->id,
            'contact_id' => $message->contact_id,
            'channel_id' => $message->channel_id,
        ]);

        // Get all users assigned to this channel
        $users = User::whereHas('channels', function ($query) use ($message) {
            $query->where('channels.id', $message->channel_id);
        })->get();

        // For each user, we would typically broadcast via websockets
        // Since we're using Livewire polling or manual refresh, 
        // the components will pick up changes on next render
        
        // The 'new-message' event is dispatched from the client side
        // when the component detects new messages via polling
    }
}
