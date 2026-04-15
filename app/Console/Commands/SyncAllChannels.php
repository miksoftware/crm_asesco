<?php

namespace App\Console\Commands;

use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncAllChannels extends Command
{
    protected $signature = 'chats:sync-all
        {--limit=500 : Máximo de mensajes por canal}
        {--include-disconnected : Incluir canales desconectados}';

    protected $description = 'Sincronizar chats de todos los canales conectados desde Evolution API';

    public function handle(): int
    {
        $query = Channel::where('is_active', true);

        if (!$this->option('include-disconnected')) {
            $query->where('status', 'connected');
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No hay canales activos/conectados para sincronizar.');
            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $this->info("Sincronizando {$channels->count()} canales (límite: {$limit} msgs c/u)...\n");

        $results = [];

        foreach ($channels as $channel) {
            $this->info("▶ {$channel->name} (ID: {$channel->id}) — {$channel->status}");

            try {
                Artisan::call('chats:import', [
                    '--channel' => $channel->id,
                    '--limit' => $limit,
                ]);

                $output = trim(Artisan::output());
                $this->line("  {$output}\n");
                $results[] = ['canal' => $channel->name, 'estado' => '✅'];
            } catch (\Exception $e) {
                $this->error("  Error: {$e->getMessage()}\n");
                $results[] = ['canal' => $channel->name, 'estado' => '❌ ' . $e->getMessage()];
            }
        }

        $this->info("\n=== Resumen ===");
        foreach ($results as $r) {
            $this->line("  {$r['estado']} {$r['canal']}");
        }

        return self::SUCCESS;
    }
}
