<?php

namespace App\Console\Commands;

use App\Services\EvolutionWebSocketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Comando daemon que conecta al WebSocket de Evolution API v2.3
 * y re-emite eventos al Broadcasting de Laravel (Reverb) en tiempo real.
 * 
 * Uso: php artisan evolution:listen
 * Supervisor: mantener activo 24/7
 * 
 * Requiere: ext-sockets (PHP)
 * Usa socket.io-client via un proceso Node.js hijo.
 */
class EvolutionListenCommand extends Command
{
    protected $signature = 'evolution:listen
        {--reconnect-delay=5 : Segundos entre intentos de reconexión}
        {--max-retries=0 : Máximo de reintentos (0 = infinito)}';

    protected $description = 'Conectar al WebSocket de Evolution API y re-emitir eventos al Broadcasting de Laravel';

    private bool $shouldRun = true;

    public function handle(EvolutionWebSocketService $wsService): int
    {
        $this->info('🔌 Iniciando listener de Evolution API WebSocket...');

        $evolutionUrl = rtrim(config('services.evolution.url', 'http://localhost:8080'), '/');
        $apiKey = config('services.evolution.api_key', '');

        if (empty($apiKey)) {
            $this->error('❌ EVOLUTION_API_KEY no está configurada en .env');
            return self::FAILURE;
        }

        $this->info("📡 URL: {$evolutionUrl}");
        $this->info("🔑 API Key: " . substr($apiKey, 0, 6) . '...');

        $reconnectDelay = (int) $this->option('reconnect-delay');
        $maxRetries = (int) $this->option('max-retries');
        $retryCount = 0;

        // Manejar señales de terminación para shutdown limpio
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () {
                $this->info('🛑 Señal SIGTERM recibida, cerrando...');
                $this->shouldRun = false;
            });
            pcntl_signal(SIGINT, function () {
                $this->info('🛑 Señal SIGINT recibida, cerrando...');
                $this->shouldRun = false;
            });
        }

        while ($this->shouldRun) {
            if ($maxRetries > 0 && $retryCount >= $maxRetries) {
                $this->error("❌ Máximo de reintentos ({$maxRetries}) alcanzado.");
                return self::FAILURE;
            }

            try {
                $this->connectAndListen($evolutionUrl, $apiKey, $wsService);
            } catch (\Exception $e) {
                $this->error("❌ Error en WebSocket: {$e->getMessage()}");
                Log::error('EvolutionListen: Error de conexión', [
                    'error' => $e->getMessage(),
                    'retry' => $retryCount,
                ]);
            }

            if ($this->shouldRun) {
                $retryCount++;
                $this->warn("🔄 Reconectando en {$reconnectDelay}s... (intento #{$retryCount})");
                sleep($reconnectDelay);
            }
        }

        $this->info('✅ Listener detenido correctamente.');
        return self::SUCCESS;
    }

    /**
     * Conectar al WebSocket de Evolution API usando un proceso Node.js hijo.
     * Node.js maneja socket.io nativamente, PHP recibe los eventos via stdin.
     */
    private function connectAndListen(string $url, string $apiKey, EvolutionWebSocketService $wsService): void
    {
        $scriptPath = base_path('scripts/evolution-ws-client.cjs');

        if (!file_exists($scriptPath)) {
            throw new \RuntimeException("Script de WebSocket no encontrado: {$scriptPath}");
        }

        // Verificar que Node.js está disponible
        $nodeCheck = shell_exec('node --version 2>&1');
        if (!$nodeCheck || !str_starts_with(trim($nodeCheck), 'v')) {
            throw new \RuntimeException('Node.js no está instalado o no está en el PATH');
        }

        $this->info('🚀 Iniciando cliente WebSocket (Node.js)...');

        // Iniciar proceso Node.js que conecta al socket.io de Evolution API
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'r'], // stdout - leeremos eventos JSON de aquí
            2 => ['pipe', 'r'], // stderr - logs del cliente
        ];

        $env = [
            'EVOLUTION_URL' => $url,
            'EVOLUTION_API_KEY' => $apiKey,
            'NODE_ENV' => 'production',
        ];

        // Heredar PATH del sistema
        if (isset($_SERVER['PATH'])) {
            $env['PATH'] = $_SERVER['PATH'];
        } elseif (isset($_ENV['PATH'])) {
            $env['PATH'] = $_ENV['PATH'];
        }

        $process = proc_open(
            ['node', $scriptPath],
            $descriptors,
            $pipes,
            base_path(),
            $env
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('No se pudo iniciar el proceso Node.js');
        }

        // Configurar stdout como no-bloqueante
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->info('✅ Proceso Node.js iniciado, esperando eventos...');

        $eventsProcessed = 0;
        $buffer = '';

        while ($this->shouldRun) {
            // Leer stderr (logs del cliente Node.js)
            $stderr = fgets($pipes[2]);
            if ($stderr) {
                $stderr = trim($stderr);
                if (str_starts_with($stderr, '[ERROR]')) {
                    $this->error("  Node: {$stderr}");
                } elseif (str_starts_with($stderr, '[WARN]')) {
                    $this->warn("  Node: {$stderr}");
                } else {
                    $this->line("  <fg=gray>Node: {$stderr}</>");
                }
            }

            // Leer stdout (eventos JSON, uno por línea)
            $line = fgets($pipes[1]);
            if ($line) {
                $buffer .= $line;

                // Procesar líneas completas
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $jsonLine = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if (empty($jsonLine)) {
                        continue;
                    }

                    $eventData = json_decode($jsonLine, true);
                    if (!$eventData || !isset($eventData['event'])) {
                        continue;
                    }

                    $event = $eventData['event'];
                    $data = $eventData['data'] ?? [];

                    try {
                        $processed = $wsService->processEvent($event, $data);
                        if ($processed) {
                            $eventsProcessed++;
                            if ($eventsProcessed % 100 === 0) {
                                $this->info("📊 Eventos procesados: {$eventsProcessed}");
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('EvolutionListen: Error procesando evento', [
                            'event' => $event,
                            'error' => $e->getMessage(),
                        ]);
                        $this->error("  Error procesando {$event}: {$e->getMessage()}");
                    }
                }
            }

            // Verificar si el proceso Node.js sigue vivo
            $status = proc_get_status($process);
            if (!$status['running']) {
                $this->warn('⚠️ Proceso Node.js terminó (exit code: ' . $status['exitcode'] . ')');
                break;
            }

            // Pequeña pausa para no consumir CPU al 100%
            usleep(10000); // 10ms
        }

        // Limpiar
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_terminate($process);
        proc_close($process);

        $this->info("📊 Total eventos procesados en esta sesión: {$eventsProcessed}");
    }
}
