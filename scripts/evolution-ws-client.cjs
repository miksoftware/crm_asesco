/**
 * Cliente WebSocket para Evolution API v2.3
 * 
 * Este script Node.js conecta al servidor socket.io de Evolution API,
 * se suscribe a eventos globales y los emite como JSON por stdout
 * para que el proceso PHP padre los procese.
 * 
 * Comunicación:
 * - stdout: eventos JSON (uno por línea) → PHP los lee
 * - stderr: logs informativos → PHP los muestra en consola
 * 
 * Eventos suscritos:
 * - MESSAGES_UPSERT: mensaje nuevo
 * - MESSAGES_UPDATE: actualización de estado
 * - SEND_MESSAGE: mensaje enviado
 * - CONNECTION_UPDATE: cambio de conexión
 */

const EVOLUTION_URL = process.env.EVOLUTION_URL || 'http://localhost:8080';
const EVOLUTION_API_KEY = process.env.EVOLUTION_API_KEY || '';

// Eventos que nos interesan
const SUBSCRIBED_EVENTS = [
    'messages.upsert',
    'messages.update', 
    'send.message',
    'connection.update',
    // Variantes en mayúsculas que Evolution API puede usar
    'MESSAGES_UPSERT',
    'MESSAGES_UPDATE',
    'SEND_MESSAGE',
    'CONNECTION_UPDATE',
];

function log(msg) {
    process.stderr.write(`[INFO] ${msg}\n`);
}

function logError(msg) {
    process.stderr.write(`[ERROR] ${msg}\n`);
}

function logWarn(msg) {
    process.stderr.write(`[WARN] ${msg}\n`);
}

/**
 * Emitir evento al proceso PHP padre via stdout.
 * Formato: JSON en una sola línea + newline.
 */
function emitEvent(event, data) {
    const payload = JSON.stringify({ event, data });
    process.stdout.write(payload + '\n');
}

async function main() {
    log(`Conectando a Evolution API: ${EVOLUTION_URL}`);

    // Importar socket.io-client dinámicamente
    let io;
    try {
        const mod = require('socket.io-client');
        io = mod.io || mod.default || mod;
    } catch (e) {
        logError(`socket.io-client no instalado. Ejecutar: npm install socket.io-client`);
        logError(`Error: ${e.message}`);
        process.exit(1);
    }

    // Conectar al WebSocket de Evolution API
    // Evolution API v2.3 usa socket.io en la raíz con autenticación via apikey
    const socket = io(EVOLUTION_URL, {
        transports: ['websocket', 'polling'],
        auth: {
            apikey: EVOLUTION_API_KEY,
        },
        // También enviar como query param (compatibilidad)
        query: {
            apikey: EVOLUTION_API_KEY,
        },
        reconnection: true,
        reconnectionAttempts: Infinity,
        reconnectionDelay: 3000,
        reconnectionDelayMax: 30000,
        timeout: 20000,
        // Habilitar eventos globales (todas las instancias)
        forceNew: true,
    });

    // === Eventos de conexión ===

    socket.on('connect', () => {
        log(`✅ Conectado al WebSocket (ID: ${socket.id})`);
        log(`Transporte: ${socket.io.engine.transport.name}`);
    });

    socket.on('disconnect', (reason) => {
        logWarn(`Desconectado: ${reason}`);
        if (reason === 'io server disconnect') {
            logWarn('El servidor cerró la conexión. Reconectando...');
            socket.connect();
        }
    });

    socket.on('connect_error', (error) => {
        logError(`Error de conexión: ${error.message}`);
    });

    socket.io.on('reconnect', (attempt) => {
        log(`🔄 Reconectado después de ${attempt} intentos`);
    });

    socket.io.on('reconnect_attempt', (attempt) => {
        if (attempt % 5 === 0) {
            log(`Intento de reconexión #${attempt}...`);
        }
    });

    // === Eventos de Evolution API ===

    // Escuchar todos los eventos suscritos
    SUBSCRIBED_EVENTS.forEach((eventName) => {
        socket.on(eventName, (data) => {
            // Normalizar nombre del evento a formato UPPER_SNAKE
            const normalizedEvent = eventName.toUpperCase().replace(/\./g, '_');
            
            // Asegurar que data tenga la estructura esperada
            let eventData = data;
            if (typeof data === 'string') {
                try {
                    eventData = JSON.parse(data);
                } catch (e) {
                    logWarn(`Datos no parseables para ${normalizedEvent}`);
                    return;
                }
            }

            emitEvent(normalizedEvent, eventData);
        });
    });

    // Escuchar evento catch-all para debug (solo loguear, no procesar)
    socket.onAny((eventName, ...args) => {
        const normalized = eventName.toUpperCase().replace(/\./g, '_');
        const isSubscribed = SUBSCRIBED_EVENTS.some(
            e => e.toUpperCase().replace(/\./g, '_') === normalized
        );
        
        if (!isSubscribed) {
            // Solo loguear eventos no suscritos para debug
            log(`Evento no suscrito: ${eventName}`);
        }
    });

    // Mantener el proceso vivo
    process.on('SIGTERM', () => {
        log('Recibida señal SIGTERM, cerrando...');
        socket.disconnect();
        process.exit(0);
    });

    process.on('SIGINT', () => {
        log('Recibida señal SIGINT, cerrando...');
        socket.disconnect();
        process.exit(0);
    });

    // Heartbeat cada 30s para verificar que la conexión sigue viva
    setInterval(() => {
        if (socket.connected) {
            log(`💓 Heartbeat OK - Conectado (ID: ${socket.id})`);
        } else {
            logWarn('💔 Heartbeat - Desconectado, esperando reconexión...');
        }
    }, 30000);
}

main().catch((err) => {
    logError(`Error fatal: ${err.message}`);
    process.exit(1);
});
