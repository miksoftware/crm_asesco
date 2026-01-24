<?php

namespace App\Events;

use App\Models\Campaign;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Campaign $campaign
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('campaigns.' . $this->campaign->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'campaign.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->campaign->id,
            'name' => $this->campaign->name,
            'status' => $this->campaign->status,
            'status_label' => $this->campaign->status_label,
            'total_recipients' => $this->campaign->total_recipients,
            'sent_count' => $this->campaign->sent_count,
            'failed_count' => $this->campaign->failed_count,
            'pending_count' => $this->campaign->pending_count,
            'progress_percentage' => $this->campaign->progress_percentage,
        ];
    }
}
