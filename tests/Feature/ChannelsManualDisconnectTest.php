<?php

namespace Tests\Feature;

use App\Livewire\Channels\Index as ChannelsIndex;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelsManualDisconnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_disconnect_is_not_overwritten_on_sync_when_api_is_still_reporting_open(): void
    {
        Http::fake([
            'http://localhost:8080/instance/fetchInstances' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'name' => 'instancia_demo',
                        'connectionStatus' => 'open',
                        'ownerJid' => '573001112233@s.whatsapp.net',
                        'integration' => 'WHATSAPP-BAILEYS',
                    ],
                ],
            ], 200),
        ]);

        $channel = Channel::factory()->create([
            'instance_name' => 'instancia_demo',
            'status' => 'disconnected',
        ]);

        $component = new ChannelsIndex();
        $component->syncFromEvolutionApi();

        $this->assertSame('disconnected', $channel->fresh()->status);
    }
}
