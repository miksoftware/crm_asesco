/**
 * CRM Chat - Cliente de tiempo real
 * 
 * Conecta a Laravel Reverb via Echo para recibir eventos de WhatsApp
 * en tiempo real: mensajes nuevos, actualizaciones de estado, etc.
 * 
 * Se integra con los componentes Livewire existentes via dispatch.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Hacer Pusher disponible globalmente (requerido por Echo)
window.Pusher = Pusher;

/**
 * Inicializar Laravel Echo con Reverb (protocolo Pusher)
 */
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Reconexión automática
    enableLogging: import.meta.env.VITE_APP_DEBUG === 'true',
});

/**
 * Estado global del chat en tiempo real
 */
window.CrmChat = {
    // Canales suscritos actualmente
    subscribedChannels: new Set(),
    subscribedContacts: new Set(),

    // Indicadores de estado de mensaje
    STATUS_ICONS: {
        pending: '🕐',
        sent: '✓',
        delivered: '✓✓',
        read: '✓✓', // azul via CSS
        failed: '✗',
    },

    /**
     * Suscribirse a un canal de WhatsApp para recibir mensajes nuevos.
     * Actualiza la lista de conversaciones en tiempo real.
     */
    subscribeToChannel(channelId) {
        if (!channelId || this.subscribedChannels.has(channelId)) return;

        const channelName = `chat.channel.${channelId}`;
        
        window.Echo.private(channelName)
            .listen('.new.message', (data) => {
                console.log('[CRM Chat] Nuevo mensaje en canal:', data);
                
                // Emitir evento Livewire para actualizar lista de conversaciones
                Livewire.dispatch('new-message', [{ 
                    channel_id: data.channel_id,
                    contact_id: data.contact_id,
                    direction: data.direction,
                }]);

                // Emitir evento para actualizar badges de notificación
                Livewire.dispatch('notifications-updated');

                // Notificación del navegador si la pestaña no está activa
                if (document.hidden && data.direction === 'incoming') {
                    this.showBrowserNotification(data);
                }

                // Sonido de notificación para mensajes entrantes
                if (data.direction === 'incoming') {
                    this.playNotificationSound();
                }
            })
            .listen('.channel.status', (data) => {
                console.log('[CRM Chat] Estado del canal actualizado:', data);
                
                // Recargar el componente Livewire para reflejar el nuevo estado
                Livewire.dispatch('channel-status-updated', [{
                    channel_id: data.channel_id,
                    status: data.status,
                }]);
            });

        this.subscribedChannels.add(channelId);
        console.log(`[CRM Chat] Suscrito al canal ${channelId}`);
    },

    /**
     * Desuscribirse de un canal de WhatsApp.
     */
    unsubscribeFromChannel(channelId) {
        if (!channelId || !this.subscribedChannels.has(channelId)) return;

        window.Echo.leave(`chat.channel.${channelId}`);
        this.subscribedChannels.delete(channelId);
        console.log(`[CRM Chat] Desuscrito del canal ${channelId}`);
    },

    /**
     * Suscribirse a una conversación específica (contacto).
     * Recibe mensajes nuevos y actualizaciones de estado en tiempo real.
     */
    subscribeToContact(contactId) {
        if (!contactId || this.subscribedContacts.has(contactId)) return;

        const channelName = `chat.contact.${contactId}`;

        window.Echo.private(channelName)
            .listen('.new.message', (data) => {
                console.log('[CRM Chat] Mensaje en conversación:', data);
                
                // Agregar mensaje al DOM instantáneamente
                this.appendMessageToChat(data);

                // Scroll automático al último mensaje
                this.scrollToBottom();

                // Marcar como leído si estamos viendo esta conversación
                Livewire.dispatch('new-message', [{
                    channel_id: data.channel_id,
                    contact_id: data.contact_id,
                    direction: data.direction,
                }]);
            })
            .listen('.message.status', (data) => {
                console.log('[CRM Chat] Estado actualizado:', data);
                
                // Actualizar ícono de estado del mensaje en el DOM
                this.updateMessageStatus(data.message_id, data.status);
            });

        this.subscribedContacts.add(contactId);
        console.log(`[CRM Chat] Suscrito a conversación ${contactId}`);
    },

    /**
     * Desuscribirse de una conversación.
     */
    unsubscribeFromContact(contactId) {
        if (!contactId || !this.subscribedContacts.has(contactId)) return;

        window.Echo.leave(`chat.contact.${contactId}`);
        this.subscribedContacts.delete(contactId);
        console.log(`[CRM Chat] Desuscrito de conversación ${contactId}`);
    },

    /**
     * Agregar un mensaje nuevo al DOM del chat sin recargar la página.
     * Crea el HTML del mensaje y lo inserta en el contenedor.
     */
    appendMessageToChat(data) {
        const container = document.getElementById('messages-container');
        if (!container) return;

        // Verificar si el mensaje ya existe en el DOM (deduplicación visual)
        if (document.querySelector(`[data-message-id="${data.id}"]`)) {
            return;
        }

        const isOutgoing = data.direction === 'outgoing';
        const time = data.sent_at 
            ? new Date(data.sent_at).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
            : new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });

        // Construir HTML del mensaje
        const messageHtml = this.buildMessageHtml(data, isOutgoing, time);

        // Insertar antes del marcador de fin o al final del contenedor
        const endMarker = container.querySelector('#messages-end');
        if (endMarker) {
            endMarker.insertAdjacentHTML('beforebegin', messageHtml);
        } else {
            container.insertAdjacentHTML('beforeend', messageHtml);
        }
    },

    /**
     * Construir HTML de un mensaje según su tipo.
     */
    buildMessageHtml(data, isOutgoing, time) {
        const alignClass = isOutgoing ? 'justify-end' : 'justify-start';
        const bgClass = isOutgoing ? 'bg-green-100' : 'bg-white';
        const statusHtml = isOutgoing ? this.getStatusHtml(data.status || 'pending', data.id) : '';
        
        let contentHtml = '';

        // Nombre del remitente en grupos
        const senderHtml = data.sender_name && !isOutgoing
            ? `<p class="text-xs font-semibold text-green-700 mb-1">${this.escapeHtml(data.sender_name)}</p>`
            : '';

        switch (data.type) {
            case 'image':
                contentHtml = data.media_url
                    ? `<img src="${data.media_url}" class="max-w-[280px] rounded-lg cursor-pointer" loading="lazy" alt="Imagen">`
                    : '';
                if (data.content) {
                    contentHtml += `<p class="text-sm text-gray-800 mt-1">${this.escapeHtml(data.content)}</p>`;
                }
                break;
            case 'audio':
                contentHtml = data.media_url
                    ? `<audio controls class="max-w-[250px]"><source src="${data.media_url}"></audio>`
                    : `<p class="text-sm text-gray-500 italic">🎵 Audio</p>`;
                break;
            case 'video':
                contentHtml = data.media_url
                    ? `<video controls class="max-w-[280px] rounded-lg"><source src="${data.media_url}"></video>`
                    : `<p class="text-sm text-gray-500 italic">🎬 Video</p>`;
                break;
            case 'document':
                contentHtml = `<p class="text-sm">📄 ${this.escapeHtml(data.content || 'Documento')}</p>`;
                if (data.media_url) {
                    contentHtml += `<a href="${data.media_url}" target="_blank" class="text-xs text-blue-600 hover:underline">Descargar</a>`;
                }
                break;
            case 'sticker':
                contentHtml = data.media_url
                    ? `<img src="${data.media_url}" class="w-32 h-32" alt="Sticker">`
                    : `<p class="text-2xl">🏷️</p>`;
                break;
            default:
                contentHtml = `<p class="text-sm text-gray-800 whitespace-pre-wrap break-words">${this.escapeHtml(data.content || '')}</p>`;
        }

        return `
            <div class="flex ${alignClass} mb-2 animate-fade-in" data-message-id="${data.id}">
                <div class="${bgClass} rounded-2xl px-3 py-2 max-w-[75%] shadow-sm border border-gray-100">
                    ${senderHtml}
                    ${contentHtml}
                    <div class="flex items-center justify-end gap-1 mt-1">
                        <span class="text-[10px] text-gray-400">${time}</span>
                        ${statusHtml}
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Obtener HTML del indicador de estado de un mensaje.
     */
    getStatusHtml(status, messageId) {
        const isRead = status === 'read';
        const colorClass = isRead ? 'text-blue-500' : 'text-gray-400';
        
        const icons = {
            pending: `<svg class="w-3.5 h-3.5 ${colorClass}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>`,
            sent: `<svg class="w-3.5 h-3.5 ${colorClass}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>`,
            delivered: `<svg class="w-4 h-3.5 ${colorClass}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12l5 5L17 6"/><path d="M7 12l5 5L23 6"/></svg>`,
            read: `<svg class="w-4 h-3.5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12l5 5L17 6"/><path d="M7 12l5 5L23 6"/></svg>`,
            failed: `<svg class="w-3.5 h-3.5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>`,
        };

        return `<span class="message-status" data-status-for="${messageId}">${icons[status] || icons.pending}</span>`;
    },

    /**
     * Actualizar el ícono de estado de un mensaje existente en el DOM.
     */
    updateMessageStatus(messageId, newStatus) {
        const statusEl = document.querySelector(`[data-status-for="${messageId}"]`);
        if (!statusEl) return;

        // Reemplazar el contenido del span con el nuevo ícono
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = this.getStatusHtml(newStatus, messageId);
        const newStatusEl = tempDiv.querySelector('.message-status');
        if (newStatusEl) {
            statusEl.innerHTML = newStatusEl.innerHTML;
            statusEl.setAttribute('data-status', newStatus);
        }
    },

    /**
     * Scroll automático al último mensaje.
     */
    scrollToBottom() {
        requestAnimationFrame(() => {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    },

    /**
     * Mostrar notificación del navegador.
     */
    showBrowserNotification(data) {
        if (!('Notification' in window)) return;

        if (Notification.permission === 'granted') {
            new Notification(data.contact_name || data.contact_phone || 'Nuevo mensaje', {
                body: data.content || 'Nuevo mensaje de WhatsApp',
                icon: '/images/logo_asesco.png',
                tag: `msg-${data.id}`,
            });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
    },

    /**
     * Reproducir sonido de notificación.
     */
    playNotificationSound() {
        try {
            // Crear un beep corto usando Web Audio API
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.1;
            
            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            oscillator.stop(ctx.currentTime + 0.3);
        } catch (e) {
            // Silenciar errores de audio (puede fallar sin interacción del usuario)
        }
    },

    /**
     * Escapar HTML para prevenir XSS.
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Limpiar todas las suscripciones.
     */
    cleanup() {
        this.subscribedChannels.forEach(id => {
            window.Echo.leave(`chat.channel.${id}`);
        });
        this.subscribedContacts.forEach(id => {
            window.Echo.leave(`chat.contact.${id}`);
        });
        this.subscribedChannels.clear();
        this.subscribedContacts.clear();
    },
};

// Solicitar permiso de notificaciones al cargar
if ('Notification' in window && Notification.permission === 'default') {
    // Esperar interacción del usuario antes de pedir permiso
    document.addEventListener('click', function requestNotifPermission() {
        Notification.requestPermission();
        document.removeEventListener('click', requestNotifPermission);
    }, { once: true });
}

// Limpiar al salir de la página
window.addEventListener('beforeunload', () => {
    window.CrmChat.cleanup();
});

console.log('[CRM Chat] Módulo de tiempo real cargado ✅');
