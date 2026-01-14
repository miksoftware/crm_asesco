# Requirements Document

## Introduction

Este documento define los requisitos para el módulo de Chat de WhatsApp integrado en ASESCO BPO. El módulo permitirá a los usuarios gestionar conversaciones de WhatsApp a través de los canales asignados, con funcionalidades específicas para operaciones de cobranza incluyendo etiquetas, acciones rápidas y notificaciones en tiempo real.

## Glossary

- **Chat_System**: Sistema principal de mensajería que gestiona las conversaciones de WhatsApp
- **Channel**: Instancia de WhatsApp conectada a través de Evolution API
- **Contact**: Persona con la que se mantiene una conversación de WhatsApp
- **Conversation**: Hilo de mensajes entre un canal y un contacto
- **Message**: Unidad individual de comunicación (texto, imagen, documento, audio)
- **Label**: Etiqueta de clasificación para contactos (ej: "Pagó", "Promesa de pago")
- **Notification_System**: Sistema que alerta sobre nuevos mensajes entrantes
- **User**: Operador del sistema con canales asignados
- **Quick_Action**: Acción predefinida para flujos de cobranza

## Requirements

### Requirement 1: Acceso a Conversaciones por Canal Asignado

**User Story:** Como usuario, quiero ver solo las conversaciones de los canales que tengo asignados, para mantener la privacidad y organización del trabajo.

#### Acceptance Criteria

1. WHEN a user accesses the chat module, THE Chat_System SHALL display only conversations from channels assigned to that user
2. WHEN a user has no channels assigned, THE Chat_System SHALL display an empty state with a message indicating no channels are available
3. WHEN a user has multiple channels assigned, THE Chat_System SHALL allow switching between channels via a channel selector
4. THE Chat_System SHALL filter all conversation queries by the user's assigned channel IDs

### Requirement 2: Lista de Conversaciones

**User Story:** Como usuario, quiero ver una lista de todas mis conversaciones ordenadas por actividad reciente, para priorizar las más urgentes.

#### Acceptance Criteria

1. THE Chat_System SHALL display conversations sorted by last message timestamp in descending order
2. WHEN displaying a conversation item, THE Chat_System SHALL show contact name, last message preview, timestamp, and unread count
3. WHEN a conversation has unread messages, THE Chat_System SHALL display a badge with the unread count
4. WHEN a user searches for a conversation, THE Chat_System SHALL filter by contact name or phone number
5. WHEN a user clicks on a conversation, THE Chat_System SHALL open the message thread and mark messages as read

### Requirement 3: Visualización de Mensajes

**User Story:** Como usuario, quiero ver los mensajes de una conversación en un formato similar a WhatsApp, para una experiencia familiar e intuitiva.

#### Acceptance Criteria

1. THE Chat_System SHALL display messages in a chat bubble format with sent messages on the right and received messages on the left
2. WHEN displaying a message, THE Chat_System SHALL show the message content, timestamp, and delivery status (sent, delivered, read)
3. WHEN a message contains media (image, document, audio), THE Chat_System SHALL render the appropriate preview or player
4. THE Chat_System SHALL load messages with infinite scroll, loading older messages when scrolling up
5. WHEN new messages arrive, THE Chat_System SHALL automatically scroll to the bottom and display them

### Requirement 4: Envío de Mensajes

**User Story:** Como usuario, quiero enviar mensajes de texto a mis contactos, para comunicarme efectivamente en las gestiones de cobranza.

#### Acceptance Criteria

1. WHEN a user types a message and presses Enter or clicks send, THE Chat_System SHALL send the message via Evolution API
2. WHEN a message is sent successfully, THE Chat_System SHALL display it in the conversation with "sent" status
3. IF a message fails to send, THEN THE Chat_System SHALL display an error indicator and allow retry
4. WHEN the input field is empty, THE Chat_System SHALL disable the send button
5. THE Chat_System SHALL support multi-line messages using Shift+Enter for line breaks

### Requirement 5: Sistema de Notificaciones

**User Story:** Como usuario, quiero recibir notificaciones de nuevos mensajes en el header, para estar al tanto de las conversaciones sin tener que estar en el módulo de chat.

#### Acceptance Criteria

1. WHEN a new message arrives, THE Notification_System SHALL increment the notification badge in the header
2. WHEN displaying notifications, THE Notification_System SHALL show contact name, channel name, and message preview
3. WHEN a user clicks on a notification, THE Notification_System SHALL navigate to the specific conversation
4. WHEN a user reads a conversation, THE Notification_System SHALL decrement the notification count accordingly
5. THE Notification_System SHALL group notifications by channel for users with multiple channels

### Requirement 6: Sistema de Etiquetas para Contactos

**User Story:** Como usuario de cobranza, quiero asignar etiquetas a los contactos, para clasificar el estado de cada gestión.

#### Acceptance Criteria

1. THE Chat_System SHALL provide predefined labels: "Pagó", "Promesa de pago", "No contesta", "Número equivocado", "Rechaza pago", "En negociación"
2. WHEN a user assigns a label to a contact, THE Chat_System SHALL display the label badge in the conversation list
3. WHEN filtering by label, THE Chat_System SHALL show only conversations with contacts that have that label
4. THE Chat_System SHALL allow multiple labels per contact
5. WHEN a user removes a label, THE Chat_System SHALL update the contact and conversation display immediately

### Requirement 7: Acciones Rápidas de Cobranza

**User Story:** Como usuario de cobranza, quiero tener acciones rápidas disponibles, para agilizar el flujo de trabajo de gestión.

#### Acceptance Criteria

1. THE Chat_System SHALL provide quick action buttons: "Registrar promesa", "Marcar como pagado", "Programar seguimiento", "Enviar recordatorio"
2. WHEN a user clicks "Registrar promesa", THE Chat_System SHALL open a modal to capture fecha y monto prometido
3. WHEN a user clicks "Marcar como pagado", THE Chat_System SHALL update the contact label and log the action
4. WHEN a user clicks "Programar seguimiento", THE Chat_System SHALL allow setting a reminder date and note
5. WHEN a user clicks "Enviar recordatorio", THE Chat_System SHALL send a predefined template message

### Requirement 8: Información del Contacto

**User Story:** Como usuario, quiero ver información detallada del contacto en el panel lateral, para tener contexto durante la conversación.

#### Acceptance Criteria

1. THE Chat_System SHALL display a contact info panel showing name, phone, labels, and notes
2. WHEN viewing contact info, THE Chat_System SHALL show conversation history summary (total messages, first contact date)
3. THE Chat_System SHALL allow editing contact name and adding notes
4. WHEN a contact has scheduled follow-ups, THE Chat_System SHALL display them in the info panel
5. THE Chat_System SHALL show payment promises history if any exist

### Requirement 9: Recepción de Mensajes en Tiempo Real

**User Story:** Como usuario, quiero recibir mensajes en tiempo real sin necesidad de refrescar la página, para mantener conversaciones fluidas.

#### Acceptance Criteria

1. WHEN Evolution API sends a webhook with a new message, THE Chat_System SHALL process and store the message immediately
2. WHEN a new message is stored, THE Chat_System SHALL broadcast it to the appropriate user's session
3. WHEN a user is viewing the conversation, THE Chat_System SHALL append the new message without page refresh
4. IF the webhook fails to process, THEN THE Chat_System SHALL log the error and retry processing

### Requirement 10: Permisos del Módulo de Chat

**User Story:** Como administrador, quiero controlar el acceso al módulo de chat mediante permisos, para gestionar quién puede ver y enviar mensajes.

#### Acceptance Criteria

1. THE Chat_System SHALL require permission "chats.ver" to access the chat module
2. THE Chat_System SHALL require permission "chats.enviar" to send messages
3. THE Chat_System SHALL require permission "chats.etiquetas" to manage contact labels
4. WHEN a user lacks required permissions, THE Chat_System SHALL hide or disable the corresponding functionality
5. THE Chat_System SHALL integrate with the existing role-based permission system
