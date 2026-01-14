# Implementation Plan: Chat Module

## Overview

Este plan implementa el módulo de Chat de WhatsApp para ASESCO BPO siguiendo el diseño aprobado. La implementación se divide en fases incrementales, comenzando con la estructura de datos, seguido de los servicios backend, componentes Livewire y finalmente la integración con webhooks.

## Tasks

- [x] 1. Configurar estructura de base de datos y modelos
  - [x] 1.1 Crear migración para tabla contacts
    - Campos: channel_id, phone_number, name, push_name, profile_picture, notes, labels (JSON), metadata (JSON)
    - Índices: unique(channel_id, phone_number), idx_phone, idx_labels
    - _Requirements: 6.1, 6.4, 8.1_

  - [x] 1.2 Crear migración para tabla messages
    - Campos: contact_id, channel_id, message_id, direction, type, content, media_url, media_mime_type, status, is_read, metadata, sent_at
    - Índices: idx_contact_channel, idx_message_id, idx_sent_at
    - _Requirements: 3.2, 4.2_

  - [x] 1.3 Crear migración para tabla notifications
    - Campos: user_id, contact_id, channel_id, message_id, type, title, body, is_read, read_at
    - Índice: idx_user_unread
    - _Requirements: 5.1, 5.2_

  - [x] 1.4 Crear migración para tabla payment_promises
    - Campos: contact_id, user_id, promised_date, promised_amount, status, notes, fulfilled_at
    - _Requirements: 7.2, 8.5_

  - [x] 1.5 Crear migración para tabla follow_ups
    - Campos: contact_id, user_id, scheduled_date, note, status, completed_at
    - _Requirements: 7.4, 8.4_

  - [x] 1.6 Crear modelo Contact con relaciones y accessors
    - Relaciones: channel, messages, paymentPromises, followUps
    - Accessors: displayName, unreadCount, lastMessage
    - _Requirements: 2.2, 2.3, 8.1_

  - [x] 1.7 Crear modelo Message con relaciones
    - Relaciones: contact, channel
    - Casts: is_read, metadata, sent_at
    - _Requirements: 3.2, 4.2_

  - [x] 1.8 Crear modelo Notification con relaciones
    - Relaciones: user, contact, channel, message
    - _Requirements: 5.1, 5.2_

  - [x] 1.9 Crear modelo PaymentPromise con relaciones
    - Relaciones: contact, user
    - _Requirements: 7.2, 8.5_

  - [x] 1.10 Crear modelo FollowUp con relaciones
    - Relaciones: contact, user
    - _Requirements: 7.4, 8.4_

  - [x] 1.11 Crear enum ContactLabel con etiquetas predefinidas
    - Valores: paid, promise, no_answer, wrong_number, rejected, negotiating
    - Métodos: label(), color()
    - _Requirements: 6.1_

- [x] 2. Implementar servicios backend

  - [x] 2.1 Crear MessageService
    - Método sendTextMessage: enviar mensaje via Evolution API
    - Método processIncomingMessage: procesar webhook y crear mensaje
    - Método getConversationMessages: obtener mensajes con paginación
    - Método markMessagesAsRead: marcar mensajes como leídos
    - _Requirements: 4.1, 4.2, 2.5, 3.4, 9.1_

  - [x] 2.2 Write property test for channel-based data isolation


    - **Property 1: Channel-Based Data Isolation**
    - **Validates: Requirements 1.1, 1.4**

  - [x] 2.3 Write property test for message pagination ordering

    - **Property 8: Pagination Ordering**
    - **Validates: Requirements 3.4**

  - [x] 2.4 Crear NotificationService
    - Método createMessageNotification: crear notificación para mensaje nuevo
    - Método getUnreadCount: obtener conteo de no leídos por usuario
    - Método getUserNotifications: obtener notificaciones del usuario
    - Método markAsRead: marcar notificación como leída
    - Método markConversationAsRead: marcar conversación como leída
    - _Requirements: 5.1, 5.2, 5.4_

  - [x] 2.5 Write property test for notification count invariant

    - **Property 11: Notification Count Invariant**
    - **Validates: Requirements 5.1, 5.4**

- [x] 3. Checkpoint - Verificar modelos y servicios
  - Ejecutar migraciones
  - Verificar relaciones de modelos
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Agregar permisos del módulo de chat

  - [x] 4.1 Actualizar RolesAndPermissionsSeeder
    - Agregar módulo 'chats' con display_name 'Chats'
    - Crear permisos: chats.ver, chats.enviar, chats.etiquetas
    - Asignar permisos al rol admin
    - _Requirements: 10.1, 10.2, 10.3, 10.5_

  - [x] 4.2 Write property test for permission-based access control

    - **Property 22: Permission-Based Access Control**
    - **Validates: Requirements 10.1, 10.2, 10.3, 10.4**

- [x] 5. Implementar componente principal de Chat

  - [x] 5.1 Crear componente Livewire Chat\Index
    - Properties: selectedChannelId, selectedContactId, search, labelFilter, messageText
    - Computed: channels, conversations, messages, selectedContact
    - Métodos: selectChannel, selectConversation, sendMessage, loadMoreMessages, markAsRead
    - _Requirements: 1.1, 1.3, 2.1, 2.4, 4.1_

  - [x] 5.2 Crear vista chat/index.blade.php con layout de 3 columnas
    - Columna izquierda: selector de canal y lista de conversaciones
    - Columna central: área de mensajes con input
    - Columna derecha: panel de información del contacto
    - _Requirements: 2.2, 3.1, 8.1_

  - [x] 5.3 Write property test for conversation sorting

    - **Property 2: Conversation Sorting by Timestamp**
    - **Validates: Requirements 2.1**


  - [x] 5.4 Write property test for search filter correctness

    - **Property 5: Search Filter Correctness**
    - **Validates: Requirements 2.4**

  - [x] 5.5 Write property test for unread count accuracy

    - **Property 4: Unread Count Accuracy**
    - **Validates: Requirements 2.3**

- [x] 6. Implementar área de mensajes

  - [x] 6.1 Crear sección de mensajes en la vista
    - Burbujas de chat: enviados a la derecha, recibidos a la izquierda
    - Mostrar contenido, timestamp y estado de entrega
    - Infinite scroll para cargar mensajes anteriores
    - _Requirements: 3.1, 3.2, 3.4_

  - [x] 6.2 Implementar envío de mensajes
    - Input con validación (no vacío)
    - Envío con Enter, Shift+Enter para salto de línea
    - Mostrar estado del mensaje (enviando, enviado, error)
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [x] 6.3 Write property test for empty message validation

    - **Property 10: Empty Message Validation**
    - **Validates: Requirements 4.4**


  - [x] 6.4 Write property test for sent message status

    - **Property 9: Sent Message Status Transition**
    - **Validates: Requirements 4.2**

- [x] 7. Checkpoint - Verificar chat básico funcional
  - Probar selección de canal y conversación
  - Probar envío de mensajes
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implementar sistema de etiquetas

  - [x] 8.1 Agregar UI de etiquetas en lista de conversaciones
    - Mostrar badges de etiquetas junto al nombre del contacto
    - Selector de filtro por etiqueta
    - _Requirements: 6.2, 6.3_

  - [x] 8.2 Implementar gestión de etiquetas en ContactInfo
    - Mostrar etiquetas actuales del contacto
    - Botones para agregar/quitar etiquetas
    - Actualización inmediata de la UI
    - _Requirements: 6.2, 6.4, 6.5_

  - [x] 8.3 Write property test for label filter correctness

    - **Property 14: Label Filter Correctness**
    - **Validates: Requirements 6.3**


  - [x] 8.4 Write property test for label management round-trip

    - **Property 15: Label Management Round-Trip**
    - **Validates: Requirements 6.2, 6.4, 6.5**

- [x] 9. Implementar panel de información del contacto

  - [x] 9.1 Crear componente Chat\ContactInfo
    - Mostrar nombre, teléfono, etiquetas, notas
    - Edición de nombre y notas
    - Resumen de historial de conversación
    - _Requirements: 8.1, 8.2, 8.3_

  - [x] 9.2 Mostrar promesas de pago e historial
    - Lista de promesas con estado
    - Lista de seguimientos programados
    - _Requirements: 8.4, 8.5_

  - [x] 9.3 Write property test for contact edit persistence

    - **Property 20: Contact Edit Persistence**
    - **Validates: Requirements 8.3**

- [x] 10. Implementar acciones rápidas de cobranza

  - [x] 10.1 Crear componente Chat\QuickActions
    - Botones: Registrar promesa, Marcar como pagado, Programar seguimiento, Enviar recordatorio
    - _Requirements: 7.1_

  - [x] 10.2 Implementar modal de registrar promesa
    - Campos: fecha prometida, monto prometido
    - Crear registro en payment_promises
    - _Requirements: 7.2_

  - [x] 10.3 Implementar acción marcar como pagado
    - Agregar etiqueta 'paid' al contacto
    - Actualizar promesa pendiente si existe
    - _Requirements: 7.3_

  - [x] 10.4 Implementar modal de programar seguimiento
    - Campos: fecha, nota
    - Crear registro en follow_ups
    - _Requirements: 7.4_

  - [x] 10.5 Implementar enviar recordatorio
    - Enviar mensaje de plantilla predefinido
    - _Requirements: 7.5_

  - [x] 10.6 Write property test for mark as paid label update

    - **Property 16: Mark as Paid Label Update**
    - **Validates: Requirements 7.3**

  - [x] 10.7 Write property test for follow-up creation

    - **Property 17: Follow-Up Creation Persistence**
    - **Validates: Requirements 7.4**

- [x] 11. Checkpoint - Verificar funcionalidades de cobranza
  - Probar gestión de etiquetas
  - Probar acciones rápidas
  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. Implementar sistema de notificaciones

  - [x] 12.1 Crear componente Chat\NotificationBadge
    - Badge con conteo de no leídos en header
    - Dropdown con lista de notificaciones
    - Navegación a conversación al hacer clic
    - _Requirements: 5.1, 5.2, 5.3_

  - [x] 12.2 Integrar NotificationBadge en layout app.blade.php
    - Reemplazar botón de notificaciones actual
    - Agrupar por canal para usuarios con múltiples canales
    - _Requirements: 5.5_

  - [x] 12.3 Implementar marcado de notificaciones como leídas
    - Al abrir conversación, marcar notificaciones relacionadas
    - Actualizar badge en tiempo real
    - _Requirements: 5.4_

  - [x] 12.4 Write property test for notification content completeness

    - **Property 12: Notification Content Completeness**
    - **Validates: Requirements 5.2**

  - [x] 12.5 Write property test for notification grouping

    - **Property 13: Notification Channel Grouping**
    - **Validates: Requirements 5.5**

- [x] 13. Implementar webhook para recepción de mensajes

  - [x] 13.1 Crear WebhookController
    - Endpoint POST /api/webhook/evolution
    - Validar payload de Evolution API
    - Procesar mensaje recibido
    - _Requirements: 9.1, 9.4_

  - [x] 13.2 Registrar ruta de webhook
    - Ruta pública sin autenticación
    - Validación por token o firma
    - _Requirements: 9.1_

  - [x] 13.3 Implementar procesamiento de mensaje entrante
    - Crear o actualizar contacto
    - Crear mensaje con dirección 'incoming'
    - Crear notificación para usuarios del canal
    - _Requirements: 9.1, 9.2_

  - [x] 13.4 Implementar broadcast de mensaje nuevo
    - Disparar evento Livewire para actualizar UI
    - Actualizar badge de notificaciones
    - _Requirements: 9.2, 9.3_

  - [x] 13.5 Write property test for webhook message processing

    - **Property 21: Webhook Message Processing**
    - **Validates: Requirements 9.1**

- [x] 14. Configurar rutas y permisos

  - [x] 14.1 Agregar rutas del módulo de chat
    - GET /chat → Chat\Index (middleware: auth, permission:chats.ver)
    - _Requirements: 10.1_

  - [x] 14.2 Agregar enlace de Chat en sidebar
    - Icono de WhatsApp
    - Visible solo con permiso chats.ver
    - Badge de notificaciones no leídas
    - _Requirements: 10.4_

  - [x] 14.3 Implementar verificación de permisos en componentes
    - Ocultar input de mensaje sin chats.enviar
    - Ocultar gestión de etiquetas sin chats.etiquetas
    - _Requirements: 10.2, 10.3, 10.4_

- [x] 15. Checkpoint final - Verificar integración completa
  - Probar flujo completo de envío y recepción
  - Probar notificaciones en tiempo real
  - Probar permisos y acceso
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- La integración con Evolution API requiere que el canal esté conectado
- Los webhooks requieren configuración en Evolution API para apuntar al endpoint
