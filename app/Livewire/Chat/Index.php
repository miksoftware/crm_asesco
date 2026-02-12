<?php

namespace App\Livewire\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Label;
use App\Models\Message;
use App\Models\User;
use App\Models\ChatTransfer;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\EvolutionApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Chat')]
class Index extends Component
{
    use WithFileUploads;

    #[Url(history: true)]
    public ?int $selectedChannelId = null;

    #[Url(history: true)]
    public ?int $selectedContactId = null;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public ?int $labelFilter = null;

    #[Url(history: true)]
    public ?string $dateFilter = null;

    #[Url(history: true)]
    public string $quickFilter = 'all';

    public string $messageText = '';

    public int $messagesLimit = 50;
    public ?int $oldestMessageId = null;
    public bool $hasMoreMessages = true;

    // File uploads
    public $mediaFile = null;
    public ?string $mediaPreview = null;
    public ?string $mediaType = null;

    // Voice recording
    public ?string $voiceRecordingBase64 = null;

    // Transfer modal
    public bool $showTransferModal = false;
    public ?int $transferToUserId = null;
    public ?int $transferToChannelId = null;
    public string $transferNote = '';

    // New label modal
    public bool $showNewLabelModal = false;
    public string $newLabelName = '';
    public string $newLabelColor = '#6b7280';

    // New chat modal
    public bool $showNewChatModal = false;
    public string $newChatNumber = '';
    public string $newChatMessage = '';
    public bool $isCheckingNumber = false;

    // Sync state
    public bool $isSyncing = false;

    // Forward message modal
    public bool $showForwardModal = false;
    public ?int $forwardMessageId = null;
    public string $forwardToNumber = '';
    public string $forwardSearch = '';

    // Permission flags
    public bool $canSend = false;
    public bool $canManageLabels = false;

    public function mount(): void
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');

        $this->canSend = $isAdmin || $user->hasPermission('chats.enviar');
        $this->canManageLabels = $isAdmin || $user->hasPermission('chats.etiquetas');

        // Auto-select first channel if none selected
        if ($this->selectedChannelId === null) {
            $firstChannel = $this->channels->first();
            if ($firstChannel) {
                $this->selectedChannelId = $firstChannel->id;
            }
        }
    }

    #[Computed]
    public function channels(): Collection
    {
        $user = auth()->user();

        // Siempre mostrar solo los canales asignados al usuario (incluso para admins)
        return $user->channels()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if the currently selected channel is connected.
     */
    #[Computed]
    public function isChannelConnected(): bool
    {
        if (!$this->selectedChannelId) {
            return false;
        }

        $channel = $this->channels->firstWhere('id', $this->selectedChannelId);
        return $channel && $channel->status === 'connected';
    }

    /**
     * Get unread message counts for all channels at once (cached).
     */
    #[Computed]
    public function channelUnreadCounts(): array
    {
        $channelIds = $this->channels->pluck('id')->toArray();
        
        if (empty($channelIds)) {
            return [];
        }

        return Message::whereIn('channel_id', $channelIds)
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->selectRaw('channel_id, COUNT(*) as count')
            ->groupBy('channel_id')
            ->pluck('count', 'channel_id')
            ->toArray();
    }

    /**
     * Get unread message count for a specific channel.
     */
    public function getChannelUnreadCount(int $channelId): int
    {
        return $this->channelUnreadCounts[$channelId] ?? 0;
    }

    #[Computed]
    public function conversations(): Collection
    {
        if ($this->selectedChannelId === null) {
            return collect();
        }

        // Use a more efficient query with subqueries instead of whereHas
        $query = Contact::where('channel_id', $this->selectedChannelId)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                  ->from('messages')
                  ->whereColumn('messages.contact_id', 'contacts.id')
                  ->limit(1);
            });

        // By default (not groups filter), show only individual chats
        // When groups filter is active, show only groups
        if ($this->quickFilter === 'groups') {
            $query->where('is_group', true);
        } else {
            $query->where(function ($q) {
                $q->where('is_group', false)->orWhereNull('is_group');
            });
        }

        // Apply search filter
        if (!empty($this->search)) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('push_name', 'like', $searchTerm)
                    ->orWhere('phone_number', 'like', $searchTerm);
            });
        }

        // Apply label filter using EXISTS (faster than whereHas)
        if (!empty($this->labelFilter)) {
            $query->whereExists(function ($q) {
                $q->selectRaw('1')
                  ->from('contact_label')
                  ->whereColumn('contact_label.contact_id', 'contacts.id')
                  ->where('contact_label.label_id', $this->labelFilter)
                  ->limit(1);
            });
        }

        // Apply quick filter with optimized queries
        switch ($this->quickFilter) {
            case 'unread':
                // Use EXISTS instead of whereHas for better performance
                $query->whereExists(function ($q) {
                    $q->selectRaw('1')
                      ->from('messages')
                      ->whereColumn('messages.contact_id', 'contacts.id')
                      ->where('messages.direction', 'incoming')
                      ->where('messages.is_read', false)
                      ->limit(1);
                });
                break;
            case 'mine':
                $query->where('assigned_user_id', auth()->id());
                break;
            case 'today':
                $todayStart = now()->startOfDay();
                $todayEnd = now()->endOfDay();
                $query->whereExists(function ($q) use ($todayStart, $todayEnd) {
                    $q->selectRaw('1')
                      ->from('messages')
                      ->whereColumn('messages.contact_id', 'contacts.id')
                      ->whereBetween('messages.sent_at', [$todayStart, $todayEnd])
                      ->limit(1);
                });
                break;
        }

        // Apply date filter
        if (!empty($this->dateFilter)) {
            $filterDate = \Carbon\Carbon::parse($this->dateFilter)->startOfDay();
            $filterDateEnd = \Carbon\Carbon::parse($this->dateFilter)->endOfDay();
            
            $query->whereExists(function ($q) use ($filterDate, $filterDateEnd) {
                $q->selectRaw('1')
                  ->from('messages')
                  ->whereColumn('messages.contact_id', 'contacts.id')
                  ->whereBetween('messages.sent_at', [$filterDate, $filterDateEnd])
                  ->limit(1);
            });
        }

        // Get contacts with last message using a subquery for sorting
        $contacts = $query
            ->addSelect(['last_message_at' => Message::selectRaw('MAX(sent_at)')
                ->whereColumn('contact_id', 'contacts.id')
            ])
            ->with(['assignedUser'])
            ->orderByDesc('last_message_at')
            ->limit(100) // Limit results for performance
            ->get();

        // Load last message for each contact efficiently
        $contactIds = $contacts->pluck('id')->toArray();
        if (!empty($contactIds)) {
            $lastMessages = Message::whereIn('contact_id', $contactIds)
                ->whereIn('id', function ($q) use ($contactIds) {
                    $q->selectRaw('MAX(id)')
                      ->from('messages')
                      ->whereIn('contact_id', $contactIds)
                      ->groupBy('contact_id');
                })
                ->get()
                ->keyBy('contact_id');

            $contacts->each(function ($contact) use ($lastMessages) {
                $contact->setRelation('messages', collect([$lastMessages->get($contact->id)])->filter());
            });
        }

        return $contacts;
    }

    #[Computed]
    public function messages(): Collection
    {
        if ($this->selectedContactId === null || $this->selectedChannelId === null) {
            return collect();
        }

        $query = Message::with('user')
            ->where('contact_id', $this->selectedContactId)
            ->where('channel_id', $this->selectedChannelId)
            ->orderBy('sent_at', 'asc')
            ->orderBy('id', 'asc');

        $messages = $query->limit($this->messagesLimit)->get();

        if ($messages->isNotEmpty()) {
            $this->oldestMessageId = $messages->first()->id;
        }

        return $messages;
    }

    #[Computed]
    public function selectedContact(): ?Contact
    {
        if ($this->selectedContactId === null) {
            return null;
        }

        return Contact::with(['paymentPromises', 'followUps', 'labelRelations'])
            ->find($this->selectedContactId);
    }

    #[Computed]
    public function availableLabels(): Collection
    {
        return Label::orderBy('is_system', 'desc')->orderBy('order')->orderBy('name')->get();
    }

    #[Computed]
    public function availableUsers(): Collection
    {
        // Incluir al usuario actual para permitir transferir entre sus propios canales
        return User::orderBy('name')
            ->get();
    }

    #[Computed]
    public function allChannels(): Collection
    {
        // Si hay un usuario seleccionado para transferir, mostrar solo sus canales
        if ($this->transferToUserId) {
            $targetUser = User::find($this->transferToUserId);
            if ($targetUser) {
                return $targetUser->channels()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
        }
        
        // Por defecto, mostrar todos los canales activos
        return Channel::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function selectChannel(int $channelId): void
    {
        $this->selectedChannelId = $channelId;
        $this->selectedContactId = null;
        $this->quickFilter = 'all'; // Reset quick filter when changing channel
        $this->resetMessages();
        unset($this->conversations);
    }

    public function selectConversation(int $contactId): void
    {
        $this->selectedContactId = $contactId;
        $this->resetMessages();
        $this->markAsRead($contactId);
    }

    public function sendMessage(): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        if (!$this->isChannelConnected) {
            $this->dispatch('toast', type: 'error', message: 'El canal está desconectado. No se pueden enviar mensajes hasta que se reconecte.');
            return;
        }

        $text = trim($this->messageText);

        if (empty($text) && !$this->mediaFile) {
            return;
        }

        if ($this->selectedContactId === null || $this->selectedChannelId === null) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona una conversación primero');
            return;
        }

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) {
            $this->dispatch('toast', type: 'error', message: 'Contacto no encontrado');
            return;
        }

        // Mark conversation as read when sending a message (agent is actively responding)
        $this->markAsRead($this->selectedContactId);

        // If there's a media file, send it
        if ($this->mediaFile) {
            $this->sendMediaMessage($contact, $text);
            return;
        }

        // Send text message
        $messageService = app(MessageService::class);

        try {
            // For groups, use the group JID; for individuals, use phone number
            $recipient = $contact->is_group ? $contact->group_jid : $contact->phone_number;
            
            $message = $messageService->sendTextMessage(
                $this->selectedChannelId,
                $recipient,
                $text,
                $contact->is_group
            );

            $this->messageText = '';

            if ($message->status === 'failed') {
                $this->dispatch('toast', type: 'error', message: 'Error al enviar el mensaje');
            }

            unset($this->messages);
        } catch (\Exception $e) {
            Log::error('Error sending message', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    public function sendMediaMessage(Contact $contact, ?string $caption = null): void
    {
        $channel = Channel::find($this->selectedChannelId);
        if (!$channel) {
            $this->dispatch('toast', type: 'error', message: 'Canal no encontrado');
            return;
        }

        $evolutionApi = app(EvolutionApiService::class);
        $recipient = $contact->is_group ? $contact->group_jid : ($contact->remote_jid ?? $contact->phone_number);

        try {
            $fileContent = file_get_contents($this->mediaFile->getRealPath());
            $base64 = base64_encode($fileContent);
            $mimeType = $this->mediaFile->getMimeType();
            $fileName = $this->mediaFile->getClientOriginalName();

            $response = null;

            if (str_starts_with($mimeType, 'image/')) {
                $response = $evolutionApi->sendImageMessage($channel->instance_name, $recipient, $base64, $caption, $mimeType);
                $type = 'image';
            } elseif (str_starts_with($mimeType, 'video/')) {
                $response = $evolutionApi->sendVideoMessage($channel->instance_name, $recipient, $base64, $caption, $mimeType);
                $type = 'video';
            } elseif (str_starts_with($mimeType, 'audio/')) {
                $response = $evolutionApi->sendAudioMessage($channel->instance_name, $recipient, $base64, $mimeType);
                $type = 'audio';
            } else {
                $response = $evolutionApi->sendDocumentMessage($channel->instance_name, $recipient, $base64, $fileName, $mimeType, $caption);
                $type = 'document';
            }

            if ($response && $response['success']) {
                // Save to local storage
                $path = 'chat-media/' . date('Y/m') . '/' . uniqid() . '_' . $fileName;
                Storage::disk('public')->put($path, $fileContent);
                $mediaUrl = Storage::disk('public')->url($path);

                // Create message record
                Message::create([
                    'contact_id' => $contact->id,
                    'channel_id' => $this->selectedChannelId,
                    'user_id' => auth()->id(),
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'direction' => 'outgoing',
                    'type' => $type,
                    'content' => $caption ?: $fileName,
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $mimeType,
                    'status' => 'sent',
                    'is_read' => true,
                    'sent_at' => now(),
                    'sender_name' => $contact->is_group ? 'Tú' : null,
                ]);

                $this->dispatch('toast', type: 'success', message: 'Archivo enviado');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al enviar el archivo');
            }
        } catch (\Exception $e) {
            Log::error('Error sending media', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }

        $this->clearMedia();
        $this->messageText = '';
        unset($this->messages);
    }

    public function updatedMediaFile(): void
    {
        if ($this->mediaFile) {
            $mimeType = $this->mediaFile->getMimeType();
            
            if (str_starts_with($mimeType, 'image/')) {
                $this->mediaType = 'image';
                $this->mediaPreview = $this->mediaFile->temporaryUrl();
            } elseif (str_starts_with($mimeType, 'video/')) {
                $this->mediaType = 'video';
                $this->mediaPreview = $this->mediaFile->temporaryUrl();
            } elseif (str_starts_with($mimeType, 'audio/')) {
                $this->mediaType = 'audio';
                $this->mediaPreview = $this->mediaFile->getClientOriginalName();
            } else {
                $this->mediaType = 'document';
                $this->mediaPreview = $this->mediaFile->getClientOriginalName();
            }
        }
    }

    public function clearMedia(): void
    {
        $this->mediaFile = null;
        $this->mediaPreview = null;
        $this->mediaType = null;
    }

    /**
     * Send a pasted/dropped image (base64)
     */
    public function sendPastedImage(string $base64Image, ?string $caption = null): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        if ($this->selectedContactId === null || $this->selectedChannelId === null) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona una conversación primero');
            return;
        }

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) {
            $this->dispatch('toast', type: 'error', message: 'Contacto no encontrado');
            return;
        }

        $channel = Channel::find($this->selectedChannelId);
        if (!$channel) {
            $this->dispatch('toast', type: 'error', message: 'Canal no encontrado');
            return;
        }

        $evolutionApi = app(EvolutionApiService::class);
        $recipient = $contact->is_group ? $contact->group_jid : ($contact->remote_jid ?? $contact->phone_number);
        $caption = trim($caption ?? '');

        try {
            $response = $evolutionApi->sendImageMessage(
                $channel->instance_name, 
                $recipient, 
                $base64Image, 
                $caption ?: null, 
                'image/png'
            );

            if ($response && $response['success']) {
                // Decode and save to storage
                $imageData = base64_decode($base64Image);
                $fileName = 'pasted_' . uniqid() . '.png';
                $path = 'chat-media/' . date('Y/m') . '/' . $fileName;
                Storage::disk('public')->put($path, $imageData);
                $mediaUrl = Storage::disk('public')->url($path);

                // Create message record
                Message::create([
                    'contact_id' => $contact->id,
                    'channel_id' => $this->selectedChannelId,
                    'user_id' => auth()->id(),
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'direction' => 'outgoing',
                    'type' => 'image',
                    'content' => $caption ?: 'Imagen',
                    'media_url' => $mediaUrl,
                    'media_mime_type' => 'image/png',
                    'status' => 'sent',
                    'is_read' => true,
                    'sent_at' => now(),
                    'sender_name' => $contact->is_group ? 'Tú' : null,
                ]);

                $this->messageText = '';
                $this->dispatch('toast', type: 'success', message: 'Imagen enviada');
                unset($this->messages);
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al enviar la imagen');
            }
        } catch (\Exception $e) {
            Log::error('Error sending pasted image', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Send voice message from base64 recording.
     */
    public function sendVoiceMessage(string $audioBase64): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        if ($this->selectedContactId === null || $this->selectedChannelId === null) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona una conversación primero');
            return;
        }

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) {
            $this->dispatch('toast', type: 'error', message: 'Contacto no encontrado');
            return;
        }

        $channel = Channel::find($this->selectedChannelId);
        if (!$channel) {
            $this->dispatch('toast', type: 'error', message: 'Canal no encontrado');
            return;
        }

        $evolutionApi = app(EvolutionApiService::class);
        $recipient = $contact->is_group ? $contact->group_jid : ($contact->remote_jid ?? $contact->phone_number);

        try {
            // Remove data URL prefix if present
            $base64Data = $audioBase64;
            if (str_contains($audioBase64, ',')) {
                $base64Data = explode(',', $audioBase64)[1];
            }

            $response = $evolutionApi->sendAudioMessage($channel->instance_name, $recipient, $base64Data);

            if ($response && $response['success']) {
                // Save audio locally
                $audioContent = base64_decode($base64Data);
                $filename = 'voice_' . uniqid() . '.ogg';
                $path = 'chat-media/' . date('Y/m') . '/' . $filename;
                Storage::disk('public')->put($path, $audioContent);
                $mediaUrl = Storage::disk('public')->url($path);

                // Create message record
                Message::create([
                    'contact_id' => $contact->id,
                    'channel_id' => $this->selectedChannelId,
                    'user_id' => auth()->id(),
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'direction' => 'outgoing',
                    'type' => 'audio',
                    'content' => '[Audio]',
                    'media_url' => $mediaUrl,
                    'media_mime_type' => 'audio/ogg; codecs=opus',
                    'status' => 'sent',
                    'is_read' => true,
                    'sent_at' => now(),
                    'sender_name' => $contact->is_group ? 'Tú' : null,
                ]);

                $this->dispatch('toast', type: 'success', message: 'Audio enviado');
                unset($this->messages);
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al enviar el audio');
            }
        } catch (\Exception $e) {
            Log::error('Error sending voice message', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    // Transfer functionality
    public function openTransferModal(): void
    {
        $this->transferToUserId = null;
        $this->transferToChannelId = $this->selectedChannelId;
        $this->transferNote = '';
        $this->showTransferModal = true;
    }

    public function updatedTransferToUserId(): void
    {
        // Refrescar la lista de canales cuando cambia el usuario
        unset($this->allChannels);
        
        // Pre-seleccionar el primer canal del usuario si existe
        if ($this->transferToUserId) {
            $firstChannel = $this->allChannels->first();
            $this->transferToChannelId = $firstChannel?->id;
        }
    }

    public function closeTransferModal(): void
    {
        $this->showTransferModal = false;
    }

    public function transferChat(): void
    {
        $this->validate([
            'transferToUserId' => 'required|exists:users,id',
            'transferToChannelId' => 'required|exists:channels,id',
        ], [
            'transferToUserId.required' => 'Selecciona un usuario',
            'transferToChannelId.required' => 'Selecciona un canal',
        ]);

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) {
            $this->dispatch('toast', type: 'error', message: 'Contacto no encontrado');
            return;
        }

        ChatTransfer::create([
            'contact_id' => $contact->id,
            'from_channel_id' => $this->selectedChannelId,
            'to_channel_id' => $this->transferToChannelId,
            'from_user_id' => auth()->id(),
            'to_user_id' => $this->transferToUserId,
            'internal_note' => trim($this->transferNote) ?: null,
            'status' => 'accepted',
            'transferred_at' => now(),
        ]);

        // Update contact
        $contact->update([
            'assigned_user_id' => $this->transferToUserId,
            'channel_id' => $this->transferToChannelId,
        ]);

        $this->closeTransferModal();
        $this->selectedContactId = null;
        unset($this->conversations);

        $this->dispatch('toast', type: 'success', message: 'Chat transferido correctamente');
    }

    // Label functionality
    public function openNewLabelModal(): void
    {
        $this->newLabelName = '';
        $this->newLabelColor = '#6b7280';
        $this->showNewLabelModal = true;
    }

    public function closeNewLabelModal(): void
    {
        $this->showNewLabelModal = false;
    }

    public function createLabel(): void
    {
        $this->validate([
            'newLabelName' => 'required|min:2|max:50',
            'newLabelColor' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        ], [
            'newLabelName.required' => 'El nombre es requerido',
            'newLabelName.min' => 'Mínimo 2 caracteres',
            'newLabelColor.required' => 'El color es requerido',
        ]);

        Label::create([
            'name' => $this->newLabelName,
            'color' => $this->newLabelColor,
            'is_system' => false,
        ]);

        $this->closeNewLabelModal();
        unset($this->availableLabels);
        $this->dispatch('toast', type: 'success', message: 'Etiqueta creada');
    }

    // New Chat functionality
    public function openNewChatModal(): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        if ($this->selectedChannelId === null) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona un canal primero');
            return;
        }

        $this->newChatNumber = '';
        $this->newChatMessage = '';
        $this->showNewChatModal = true;
    }

    public function closeNewChatModal(): void
    {
        $this->showNewChatModal = false;
        $this->newChatNumber = '';
        $this->newChatMessage = '';
        $this->isCheckingNumber = false;
    }

    public function startNewChat(): void
    {
        $this->validate([
            'newChatNumber' => 'required|min:10|max:20',
            'newChatMessage' => 'required|min:1|max:4096',
        ], [
            'newChatNumber.required' => 'El número es requerido',
            'newChatNumber.min' => 'El número debe tener al menos 10 dígitos',
            'newChatMessage.required' => 'El mensaje es requerido',
        ]);

        $channel = Channel::find($this->selectedChannelId);
        if (!$channel) {
            $this->dispatch('toast', type: 'error', message: 'Canal no encontrado');
            return;
        }

        $this->isCheckingNumber = true;
        $evolutionApi = app(EvolutionApiService::class);

        try {
            // Normalize phone number - remove all non-numeric characters
            $phoneNumber = preg_replace('/[^0-9]/', '', $this->newChatNumber);

            // Validate minimum length
            if (strlen($phoneNumber) < 10) {
                $this->isCheckingNumber = false;
                $this->dispatch('toast', type: 'error', message: 'El número debe tener al menos 10 dígitos');
                return;
            }

            // Check if number exists on WhatsApp
            $checkResult = $evolutionApi->checkWhatsAppNumber($channel->instance_name, $phoneNumber);

            if (!$checkResult['success']) {
                $this->isCheckingNumber = false;
                $this->dispatch('toast', type: 'error', message: 'Error al verificar el número');
                return;
            }

            if (!$checkResult['exists']) {
                $this->isCheckingNumber = false;
                $this->dispatch('toast', type: 'error', message: 'El número no está registrado en WhatsApp');
                return;
            }

            // Get the REAL JID from WhatsApp (this is the actual number registered)
            $remoteJid = $checkResult['jid'] ?? $phoneNumber . '@s.whatsapp.net';
            
            // Extract the REAL phone number from the JID (may be different from input)
            $realPhoneNumber = $this->extractPhoneNumber($remoteJid) ?? $phoneNumber;

            // Find existing contact by remote_jid OR real phone number
            $contact = Contact::where('channel_id', $this->selectedChannelId)
                ->where(function ($q) use ($remoteJid, $realPhoneNumber, $phoneNumber) {
                    $q->where('remote_jid', $remoteJid)
                      ->orWhere('phone_number', $realPhoneNumber)
                      ->orWhere('phone_number', $phoneNumber);
                })
                ->first();

            if (!$contact) {
                // Create new contact with the REAL phone number
                $contact = Contact::create([
                    'channel_id' => $this->selectedChannelId,
                    'phone_number' => $realPhoneNumber,
                    'remote_jid' => $remoteJid,
                ]);
            } else {
                // Update contact with correct JID if needed
                $contact->update([
                    'remote_jid' => $remoteJid,
                    'phone_number' => $realPhoneNumber, // Use the real number
                ]);
            }

            // Send the message using the verified JID
            $response = $evolutionApi->sendTextMessage(
                $channel->instance_name,
                $remoteJid,
                $this->newChatMessage
            );

            if ($response['success']) {
                // Create message record
                Message::create([
                    'contact_id' => $contact->id,
                    'channel_id' => $this->selectedChannelId,
                    'user_id' => auth()->id(),
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'direction' => 'outgoing',
                    'type' => 'text',
                    'content' => $this->newChatMessage,
                    'status' => 'sent',
                    'is_read' => true,
                    'sent_at' => now(),
                ]);

                $this->closeNewChatModal();
                
                // Select the new conversation
                $this->selectedContactId = $contact->id;
                unset($this->conversations);
                unset($this->messages);

                $this->dispatch('toast', type: 'success', message: 'Conversación iniciada');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al enviar el mensaje');
            }

        } catch (\Exception $e) {
            Log::error('Error starting new chat', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }

        $this->isCheckingNumber = false;
    }

    public function addLabelToContact(int $labelId): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso');
            return;
        }

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) return;

        $contact->labelRelations()->syncWithoutDetaching([$labelId]);
        
        unset($this->selectedContact);
        unset($this->conversations);
        $this->dispatch('toast', type: 'success', message: 'Etiqueta agregada');
    }

    public function removeLabelFromContact(int $labelId): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso');
            return;
        }

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) return;

        $contact->labelRelations()->detach($labelId);
        
        unset($this->selectedContact);
        unset($this->conversations);
        $this->dispatch('toast', type: 'success', message: 'Etiqueta eliminada');
    }

    public function retryMessage(int $messageId): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso');
            return;
        }

        $message = Message::find($messageId);
        if (!$message || $message->status !== 'failed') return;

        $contact = Contact::find($message->contact_id);
        if (!$contact) return;

        $channel = Channel::find($message->channel_id);
        $evolutionApi = app(EvolutionApiService::class);

        try {
            $message->update(['status' => 'pending']);
            
            $response = $evolutionApi->sendTextMessage(
                $channel->instance_name,
                $contact->is_group ? $contact->group_jid : ($contact->remote_jid ?? $contact->phone_number),
                $message->content
            );

            if ($response['success']) {
                $message->update([
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'status' => 'sent',
                ]);
                $this->dispatch('toast', type: 'success', message: 'Mensaje reenviado');
            } else {
                $message->update(['status' => 'failed']);
                $this->dispatch('toast', type: 'error', message: 'Error al reenviar');
            }
        } catch (\Exception $e) {
            $message->update(['status' => 'failed']);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }

        unset($this->messages);
    }

    /**
     * Abrir modal de reenvío de mensaje.
     */
    public function openForwardModal(int $messageId): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        $this->forwardMessageId = $messageId;
        $this->forwardToNumber = '';
        $this->forwardSearch = '';
        $this->showForwardModal = true;
    }

    public function closeForwardModal(): void
    {
        $this->showForwardModal = false;
        $this->forwardMessageId = null;
        $this->forwardToNumber = '';
        $this->forwardSearch = '';
    }

    /**
     * Contactos disponibles para reenviar (del mismo canal).
     */
    #[Computed]
    public function forwardContacts(): Collection
    {
        if (!$this->selectedChannelId) {
            return collect();
        }

        $query = Contact::where('channel_id', $this->selectedChannelId)
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '');

        if ($this->forwardSearch) {
            $search = $this->forwardSearch;
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('push_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('updated_at', 'desc')->limit(20)->get();
    }

    /**
     * Seleccionar contacto para reenviar.
     */
    public function selectForwardContact(string $phoneNumber): void
    {
        $this->forwardToNumber = $phoneNumber;
    }

    /**
     * Reenviar mensaje a otro número.
     */
    public function forwardMessage(): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        $number = trim($this->forwardToNumber);
        if (empty($number)) {
            $this->dispatch('toast', type: 'error', message: 'Ingresa un número de destino');
            return;
        }

        $message = Message::find($this->forwardMessageId);
        if (!$message) {
            $this->dispatch('toast', type: 'error', message: 'Mensaje no encontrado');
            return;
        }

        $channel = Channel::find($this->selectedChannelId);
        if (!$channel || $channel->status !== 'connected') {
            $this->dispatch('toast', type: 'error', message: 'El canal no está conectado');
            return;
        }

        // Normalizar número
        $number = preg_replace('/[^0-9]/', '', $number);

        $evolutionApi = app(EvolutionApiService::class);

        try {
            $response = null;

            if ($message->type === 'text') {
                // Reenviar texto
                $response = $evolutionApi->sendTextMessage(
                    $channel->instance_name,
                    $number,
                    $message->content ?? ''
                );
            } elseif (in_array($message->type, ['image', 'video', 'audio', 'document']) && $message->media_url) {
                // Reenviar media: leer archivo y convertir a base64
                $mediaPath = str_replace(Storage::disk('public')->url(''), '', $message->media_url);
                
                if (Storage::disk('public')->exists($mediaPath)) {
                    $fileContent = Storage::disk('public')->get($mediaPath);
                    $base64 = base64_encode($fileContent);
                    $mimeType = $message->media_mime_type ?? 'application/octet-stream';

                    if ($message->type === 'image') {
                        $response = $evolutionApi->sendImageMessage(
                            $channel->instance_name, $number, $base64, $message->content, $mimeType
                        );
                    } elseif ($message->type === 'video') {
                        $response = $evolutionApi->sendVideoMessage(
                            $channel->instance_name, $number, $base64, $message->content, $mimeType
                        );
                    } elseif ($message->type === 'audio') {
                        $response = $evolutionApi->sendAudioMessage(
                            $channel->instance_name, $number, $base64, $mimeType
                        );
                    } elseif ($message->type === 'document') {
                        $fileName = $message->content ?? 'documento';
                        $response = $evolutionApi->sendDocumentMessage(
                            $channel->instance_name, $number, $base64, $fileName, $mimeType
                        );
                    }
                } else {
                    // Si no hay archivo local, reenviar como texto con indicación
                    $text = "↪ Mensaje reenviado:\n" . ($message->content ?? '[Media no disponible]');
                    $response = $evolutionApi->sendTextMessage($channel->instance_name, $number, $text);
                }
            } else {
                // Para otros tipos, reenviar como texto
                $text = $message->content ?? '[Mensaje reenviado]';
                $response = $evolutionApi->sendTextMessage($channel->instance_name, $number, $text);
            }

            if ($response && $response['success']) {
                // Buscar o crear contacto destino
                $destContact = Contact::firstOrCreate(
                    ['channel_id' => $channel->id, 'phone_number' => $number],
                    ['remote_jid' => $number . '@s.whatsapp.net']
                );

                // Guardar el mensaje reenviado
                Message::create([
                    'contact_id' => $destContact->id,
                    'channel_id' => $channel->id,
                    'user_id' => auth()->id(),
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'direction' => 'outgoing',
                    'type' => ($message->type === 'text' || !$message->media_url) ? 'text' : $message->type,
                    'content' => $message->content,
                    'media_url' => ($message->media_url && in_array($message->type, ['image', 'video', 'audio', 'document'])) ? $message->media_url : null,
                    'media_mime_type' => $message->media_mime_type,
                    'status' => 'sent',
                    'is_read' => true,
                    'sent_at' => now(),
                ]);

                $this->closeForwardModal();
                $this->dispatch('toast', type: 'success', message: 'Mensaje reenviado a ' . $number);
            } else {
                $error = $response['error'] ?? 'Error desconocido';
                $this->dispatch('toast', type: 'error', message: 'Error al reenviar: ' . $error);
            }
        } catch (\Exception $e) {
            Log::error('Error forwarding message', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    public function loadMoreMessages(): void
    {
        if (!$this->hasMoreMessages || $this->oldestMessageId === null) return;

        $messageService = app(MessageService::class);
        $olderMessages = $messageService->getConversationMessages(
            $this->selectedContactId,
            $this->selectedChannelId,
            $this->messagesLimit,
            $this->oldestMessageId
        );

        if ($olderMessages->isEmpty()) {
            $this->hasMoreMessages = false;
            return;
        }

        $this->oldestMessageId = $olderMessages->first()->id;
        $this->messagesLimit += $olderMessages->count();
        unset($this->messages);
    }

    public function markAsRead(int $contactId): void
    {
        if ($this->selectedChannelId === null) return;

        $messageService = app(MessageService::class);
        $messageService->markMessagesAsRead($contactId, $this->selectedChannelId);

        $notificationService = app(NotificationService::class);
        $notificationService->markConversationAsRead(auth()->id(), $contactId, $this->selectedChannelId);

        // Clear all caches to ensure fresh data
        unset($this->channelUnreadCounts);
        unset($this->conversations);

        // Dispatch to notification components to update the count
        $this->dispatch('notifications-updated')->to(NotificationBadge::class);
        $this->dispatch('notifications-updated')->to(SidebarBadge::class);
    }

    public function markAsUnread(int $contactId): void
    {
        if ($this->selectedChannelId === null) return;

        // Mark last incoming message as unread
        Message::where('contact_id', $contactId)
            ->where('channel_id', $this->selectedChannelId)
            ->where('direction', 'incoming')
            ->orderByDesc('sent_at')
            ->limit(1)
            ->update(['is_read' => false]);

        // Create notification for this conversation
        $notificationService = app(NotificationService::class);
        $lastMessage = Message::where('contact_id', $contactId)
            ->where('channel_id', $this->selectedChannelId)
            ->where('direction', 'incoming')
            ->orderByDesc('sent_at')
            ->first();

        if ($lastMessage) {
            $notificationService->createMessageNotification($lastMessage);
        }

        $this->dispatch('notifications-updated');
        unset($this->conversations);
        
        $this->dispatch('toast', type: 'success', message: 'Chat marcado como no leído');
    }

    public function updatingSearch(): void
    {
        $this->selectedContactId = null;
        $this->resetMessages();
    }

    public function updatingLabelFilter(): void
    {
        $this->selectedContactId = null;
        $this->resetMessages();
    }

    public function clearLabelFilter(): void
    {
        $this->labelFilter = null;
        $this->selectedContactId = null;
        $this->resetMessages();
    }

    public function clearDateFilter(): void
    {
        $this->dateFilter = null;
        $this->selectedContactId = null;
        $this->resetMessages();
    }

    public function updatingDateFilter(): void
    {
        $this->selectedContactId = null;
        $this->resetMessages();
    }

    public function setQuickFilter(string $filter): void
    {
        $this->quickFilter = $filter;
        $this->selectedContactId = null;
        $this->resetMessages();
        unset($this->conversations);
    }

    private function resetMessages(): void
    {
        $this->messagesLimit = 50;
        $this->oldestMessageId = null;
        $this->hasMoreMessages = true;
        unset($this->messages);
    }

    #[On('new-message')]
    public function handleNewMessage(array $data): void
    {
        if (isset($data['channel_id']) && $data['channel_id'] == $this->selectedChannelId) {
            unset($this->conversations);

            if (isset($data['contact_id']) && $data['contact_id'] == $this->selectedContactId) {
                unset($this->messages);
                $this->markAsRead($data['contact_id']);
            }
        }
    }

    #[On('contact-updated')]
    public function handleContactUpdated(): void
    {
        unset($this->conversations);
        unset($this->selectedContact);
    }

    /**
     * Sync messages from Evolution API for the current channel.
     * Optimized to avoid timeout - does NOT download media during sync.
     */
    public function syncMessages(): void
    {
        if ($this->selectedChannelId === null) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona un canal primero');
            return;
        }

        $channel = Channel::find($this->selectedChannelId);
        if (!$channel) {
            $this->dispatch('toast', type: 'error', message: 'Canal no encontrado');
            return;
        }

        $this->isSyncing = true;
        $evolutionApi = app(EvolutionApiService::class);

        try {
            // First, clean up invalid contacts
            $cleaned = $this->cleanupInvalidContacts($channel->id);
            
            $imported = 0;
            $page = 1;
            $maxPages = 50; // Increased to get more messages

            while ($page <= $maxPages) {
                $result = $evolutionApi->fetchAllMessages($channel->instance_name, $page, 50); // Reduced batch size

                if (!$result['success']) {
                    Log::error('Error fetching messages from Evolution API', ['error' => $result['error'] ?? 'Unknown']);
                    break;
                }

                $messagesData = $result['data']['messages'] ?? $result['data'] ?? [];
                $records = $messagesData['records'] ?? $messagesData ?? [];

                if (empty($records)) {
                    break;
                }

                foreach ($records as $msgData) {
                    if ($this->processImportedMessageFast($channel, $msgData)) {
                        $imported++;
                    }
                }

                $page++;
            }

            $this->isSyncing = false;
            unset($this->conversations);
            unset($this->messages);
            unset($this->hasConversations);

            $message = '';
            if ($cleaned > 0) {
                $message .= "Se limpiaron {$cleaned} contactos inválidos. ";
            }
            if ($imported > 0) {
                $message .= "Se importaron {$imported} mensajes.";
                $this->dispatch('toast', type: 'success', message: trim($message));
            } elseif ($cleaned > 0) {
                $this->dispatch('toast', type: 'success', message: trim($message));
            } else {
                $this->dispatch('toast', type: 'info', message: 'No hay cambios para sincronizar');
            }

        } catch (\Exception $e) {
            $this->isSyncing = false;
            Log::error('Error syncing messages', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error al sincronizar: ' . $e->getMessage());
        }
    }

    /**
     * Clean up invalid contacts (status broadcasts, groups, newsletters, and LID-only contacts)
     */
    private function cleanupInvalidContacts(int $channelId): int
    {
        $invalidContacts = Contact::where('channel_id', $channelId)
            ->where(function ($query) {
                $query
                    // Status broadcasts
                    ->where('remote_jid', 'like', '%@broadcast%')
                    ->orWhere('remote_jid', 'like', '%status@%')
                    // Newsletter
                    ->orWhere('remote_jid', 'like', '%@newsletter%')
                    // Groups
                    ->orWhere('remote_jid', 'like', '%@g.us%')
                    // "Você" status contacts
                    ->orWhereRaw("LOWER(TRIM(push_name)) = 'você'")
                    ->orWhereRaw("LOWER(TRIM(push_name)) = 'voce'")
                    ->orWhereRaw("LOWER(TRIM(name)) = 'você'")
                    ->orWhereRaw("LOWER(TRIM(name)) = 'voce'")
                    // LID contacts that don't have real phone numbers (not starting with valid country code)
                    // Real phone numbers start with 1-9 and have 10-15 digits
                    ->orWhereRaw("phone_number NOT REGEXP '^[1-9][0-9]{9,14}$'");
            })
            ->get();

        $count = $invalidContacts->count();

        foreach ($invalidContacts as $contact) {
            // Delete messages first (due to foreign key)
            $contact->messages()->delete();
            // Delete labels
            $contact->labelRelations()->detach();
            // Delete the contact
            $contact->delete();
        }

        if ($count > 0) {
            Log::info("Cleaned up {$count} invalid contacts from channel {$channelId}");
        }

        return $count;
    }

    /**
     * Process a single message from Evolution API import.
     */
    private function processImportedMessage(Channel $channel, array $msgData, EvolutionApiService $evolutionApi): bool
    {
        return $this->processImportedMessageFast($channel, $msgData);
    }

    /**
     * Fast message import - does NOT download media to avoid timeout.
     * Media will be downloaded on-demand when viewing the message.
     * 
     * IMPORTANT: Handles @lid JIDs by:
     * 1. First checking remoteJidAlt field (contains real phone when remoteJid is LID)
     * 2. Checking the lid_mappings table for known LID → phone mappings
     * 3. Skipping LIDs that don't have a mapping and aren't real phone numbers
     */
    private function processImportedMessageFast(Channel $channel, array $msgData): bool
    {
        $key = $msgData['key'] ?? [];
        $messageId = $key['id'] ?? null;
        $remoteJid = $key['remoteJid'] ?? null;
        $remoteJidAlt = $key['remoteJidAlt'] ?? null; // ⭐ Evolution API sends real phone here when remoteJid is LID

        if (!$messageId || !$remoteJid) {
            return false;
        }

        // Check if this is a group message
        $isGroupMessage = str_contains($remoteJid, '@g.us');

        // Skip status/broadcast messages
        if (str_contains($remoteJid, '@broadcast') || str_contains($remoteJid, 'status@')) {
            return false;
        }

        // Skip newsletter messages
        if (str_contains($remoteJid, '@newsletter')) {
            return false;
        }

        // Check if message already exists
        if (Message::where('message_id', $messageId)->exists()) {
            return false;
        }

        // Skip "Você" contacts (WhatsApp status)
        $pushName = $msgData['pushName'] ?? null;
        $pushNameLower = $pushName ? strtolower(trim($pushName)) : '';
        if (in_array($pushNameLower, ['você', 'voce'])) {
            return false;
        }

        // Extract the identifier from JID
        $jidPart = explode('@', $remoteJid)[0];
        
        // ⭐ GROUP MESSAGE HANDLING
        $senderName = null;
        $senderPhone = null;
        
        if ($isGroupMessage) {
            $groupJid = $remoteJid;
            $groupId = $jidPart;
            $groupName = $msgData['groupName'] ?? $pushName ?? null;
            
            // Extract sender info from participant field
            $participant = $key['participant'] ?? null;
            if ($participant) {
                $participantPart = explode('@', $participant)[0];
                $senderPhone = explode(':', $participantPart)[0];
            }
            $senderName = $pushName;
            
            $isFromMe = $key['fromMe'] ?? false;
            if ($isFromMe) {
                $senderName = 'Tú';
                $senderPhone = $channel->phone_number;
            }
            
            // Find or create group contact
            $contact = Contact::where('channel_id', $channel->id)
                ->where('is_group', true)
                ->where('group_jid', $groupJid)
                ->first();
            
            if (!$contact) {
                $contact = Contact::create([
                    'channel_id' => $channel->id,
                    'phone_number' => $groupId,
                    'remote_jid' => $groupJid,
                    'is_group' => true,
                    'group_jid' => $groupJid,
                    'name' => $groupName,
                    'push_name' => $groupName,
                ]);
            } else {
                if ($groupName && !$contact->name) {
                    $contact->update(['name' => $groupName, 'push_name' => $groupName]);
                }
            }
        } else {
            // ⭐ INDIVIDUAL MESSAGE HANDLING (existing LID logic)
        
        // Check if this is a LID (contains @lid or has : in the identifier)
        $isLid = str_contains($remoteJid, '@lid') || str_contains($jidPart, ':');
        
        // For LID JIDs, extract just the numeric part before any ':'
        $cleanJidPart = explode(':', $jidPart)[0];
        
        // Determine if this looks like a real phone number
        // Real phone numbers: 10-15 digits, start with country code (1-9)
        $isRealPhoneNumber = preg_match('/^[1-9]\d{9,14}$/', $cleanJidPart);
        
        $phoneNumber = null;
        
        // ⭐ PRIORITY 1: Check remoteJidAlt first (Evolution API sends real phone here)
        if ($remoteJidAlt) {
            $altJidPart = explode('@', $remoteJidAlt)[0];
            $cleanAltPart = explode(':', $altJidPart)[0];
            $isAltRealPhone = preg_match('/^[1-9]\d{9,14}$/', $cleanAltPart);
            
            if ($isAltRealPhone) {
                $phoneNumber = $cleanAltPart;
                
                // ⭐ AUTO-CREATE LID MAPPING if remoteJid is a LID
                if ($isLid && !$isRealPhoneNumber) {
                    \App\Models\LidMapping::createMapping($cleanJidPart, $phoneNumber, $messageId, $channel->id);
                    Log::info('LID mapping created from sync remoteJidAlt', [
                        'lid' => $cleanJidPart,
                        'phone' => $phoneNumber,
                        'messageId' => $messageId,
                    ]);
                }
            }
        }
        
        // ⭐ PRIORITY 2: If no phone from remoteJidAlt, check if remoteJid itself is a real phone
        if (!$phoneNumber && $isRealPhoneNumber) {
            $phoneNumber = $cleanJidPart;
        }
        
        // ⭐ PRIORITY 3: Check LID mapping table
        if (!$phoneNumber && $isLid && !$isRealPhoneNumber) {
            $phoneNumber = \App\Models\LidMapping::findPhoneByLid($cleanJidPart);
            
            if ($phoneNumber) {
                Log::debug('LID resolved from mapping table', [
                    'lid' => $cleanJidPart,
                    'phone' => $phoneNumber,
                ]);
            }
        }
        
        // ⭐ PRIORITY 4: Last resort - use whatever we have
        if (!$phoneNumber) {
            if ($isRealPhoneNumber) {
                $phoneNumber = $cleanJidPart;
            } else {
                // This is a true LID with no mapping - skip
                Log::debug('Skipping LID message - no mapping found', [
                    'remoteJid' => $remoteJid,
                    'remoteJidAlt' => $remoteJidAlt,
                    'lid' => $cleanJidPart,
                ]);
                return false;
            }
        }

        // Minimal validation - just ensure we have something
        if (empty($phoneNumber) || strlen($phoneNumber) < 8) {
            return false;
        }

        // Search for existing contact by phone number (to unify @lid and @s.whatsapp.net)
        $contact = Contact::where('channel_id', $channel->id)
            ->where('phone_number', $phoneNumber)
            ->first();

        // Standard JID format for sending messages
        $standardJid = $phoneNumber . '@s.whatsapp.net';

        if (!$contact) {
            // Create new contact
            $contact = Contact::create([
                'channel_id' => $channel->id,
                'phone_number' => $phoneNumber,
                'remote_jid' => $standardJid,
                'push_name' => $pushName,
            ]);
        } else {
            // Update contact if needed
            $updates = [];
            
            if ($pushName && !$contact->push_name) {
                $updates['push_name'] = $pushName;
            }
            
            // Always ensure remote_jid is in standard format
            if ($contact->remote_jid !== $standardJid) {
                $updates['remote_jid'] = $standardJid;
            }
            
            if (!empty($updates)) {
                $contact->update($updates);
            }
        }
        } // End of individual message handling (else block)

        // Extract message content and type
        $messageContent = $msgData['message'] ?? [];
        
        // Determine type and content
        $type = 'text';
        $content = null;
        $mediaMimeType = null;

        if (isset($messageContent['conversation'])) {
            $type = 'text';
            $content = $messageContent['conversation'];
        } elseif (isset($messageContent['extendedTextMessage'])) {
            $type = 'text';
            $content = $messageContent['extendedTextMessage']['text'] ?? null;
        } elseif (isset($messageContent['imageMessage'])) {
            $type = 'image';
            $content = $messageContent['imageMessage']['caption'] ?? '[Imagen]';
            $mediaMimeType = $messageContent['imageMessage']['mimetype'] ?? 'image/jpeg';
        } elseif (isset($messageContent['videoMessage'])) {
            $type = 'video';
            $content = $messageContent['videoMessage']['caption'] ?? '[Video]';
            $mediaMimeType = $messageContent['videoMessage']['mimetype'] ?? 'video/mp4';
        } elseif (isset($messageContent['audioMessage']) || isset($messageContent['pttMessage'])) {
            $type = 'audio';
            $content = '[Audio]';
            $audioMsg = $messageContent['audioMessage'] ?? $messageContent['pttMessage'] ?? [];
            $mediaMimeType = $audioMsg['mimetype'] ?? 'audio/ogg; codecs=opus';
        } elseif (isset($messageContent['documentMessage']) || isset($messageContent['documentWithCaptionMessage'])) {
            $type = 'document';
            $docMsg = $messageContent['documentMessage'] ?? $messageContent['documentWithCaptionMessage']['message']['documentMessage'] ?? [];
            $content = $docMsg['fileName'] ?? $docMsg['title'] ?? '[Documento]';
            $mediaMimeType = $docMsg['mimetype'] ?? 'application/octet-stream';
        } elseif (isset($messageContent['contactMessage']) || isset($messageContent['contactsArrayMessage'])) {
            $type = 'contact';
            $contactMsg = $messageContent['contactMessage'] ?? ($messageContent['contactsArrayMessage']['contacts'][0] ?? []);
            $content = $contactMsg['displayName'] ?? '[Contacto compartido]';
        } elseif (isset($messageContent['locationMessage']) || isset($messageContent['liveLocationMessage'])) {
            $type = 'location';
            $locMsg = $messageContent['locationMessage'] ?? $messageContent['liveLocationMessage'] ?? [];
            $content = $locMsg['name'] ?? $locMsg['address'] ?? '[Ubicación]';
        } elseif (isset($messageContent['stickerMessage'])) {
            $type = 'sticker';
            $content = '[Sticker]';
        } elseif (isset($messageContent['reactionMessage'])) {
            return false;
        } elseif (isset($messageContent['protocolMessage'])) {
            $protoType = $messageContent['protocolMessage']['type'] ?? null;
            if ($protoType === 'REVOKE' || $protoType === 0) {
                $type = 'deleted';
                $content = '[Mensaje eliminado]';
            } else {
                return false;
            }
        } elseif (isset($messageContent['viewOnceMessage']) || isset($messageContent['viewOnceMessageV2'])) {
            $type = 'image';
            $content = '[Vista única]';
        } elseif (isset($messageContent['pollCreationMessage']) || isset($messageContent['pollCreationMessageV3'])) {
            $type = 'text';
            $pollMsg = $messageContent['pollCreationMessage'] ?? $messageContent['pollCreationMessageV3'] ?? [];
            $content = '📊 ' . ($pollMsg['name'] ?? 'Encuesta');
        } else {
            $type = 'other';
            $content = '[Mensaje no soportado]';
        }

        // Parse timestamp
        $timestamp = $msgData['messageTimestamp'] ?? null;
        $sentAt = $timestamp 
            ? \Carbon\Carbon::createFromTimestamp($timestamp, 'UTC')->setTimezone(config('app.timezone'))
            : now();

        // Determine direction
        $isFromMe = $key['fromMe'] ?? false;
        $direction = $isFromMe ? 'outgoing' : 'incoming';
        $status = $isFromMe ? 'sent' : 'delivered';

        // Create message (without media_url - will be loaded on-demand)
        Message::create([
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'message_id' => $messageId,
            'content' => $content ?: null,
            'type' => $type,
            'direction' => $direction,
            'status' => $status,
            'media_url' => null,
            'media_mime_type' => $mediaMimeType,
            'sender_name' => $senderName,
            'sender_phone' => $senderPhone,
            'sent_at' => $sentAt,
            'is_read' => true,
        ]);

        return true;
    }

    /**
     * Get file extension from MIME type.
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/ogg; codecs=opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];

        return $map[$mimeType] ?? 'bin';
    }

    /**
     * Extract phone number from WhatsApp JID.
     */
    private function extractPhoneNumber(string $remoteJid): ?string
    {
        // Format: 573001234567@s.whatsapp.net or 573001234567:123@lid
        if (preg_match('/^(\d+)[@:]/', $remoteJid, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Check if current channel has any conversations.
     */
    #[Computed]
    public function hasConversations(): bool
    {
        if ($this->selectedChannelId === null) {
            return false;
        }

        return Contact::where('channel_id', $this->selectedChannelId)
            ->whereHas('messages')
            ->exists();
    }

    /**
     * Load media for a message on-demand.
     */
    public function loadMessageMedia(int $messageId): void
    {
        $message = Message::find($messageId);
        if (!$message || $message->media_url) {
            return; // Already has media or not found
        }

        if (!in_array($message->type, ['image', 'audio', 'video', 'document', 'sticker'])) {
            return; // Not a media message
        }

        $contact = Contact::find($message->contact_id);
        $channel = Channel::find($message->channel_id);
        
        if (!$contact || !$channel) {
            return;
        }

        $evolutionApi = app(EvolutionApiService::class);

        try {
            $remoteJid = $contact->is_group ? $contact->group_jid : ($contact->remote_jid ?? $contact->phone_number . '@s.whatsapp.net');
            $mediaResult = $evolutionApi->getMediaBase64($channel->instance_name, $message->message_id, $remoteJid);
            
            if ($mediaResult['success'] && !empty($mediaResult['data']['base64'])) {
                $base64Data = $mediaResult['data']['base64'];
                
                $extension = $this->getExtensionFromMimeType($message->media_mime_type ?? 'application/octet-stream');
                $filename = $message->type . '_' . uniqid() . '.' . $extension;
                $path = 'chat-media/' . date('Y/m') . '/' . $filename;
                
                Storage::disk('public')->put($path, base64_decode($base64Data));
                $mediaUrl = Storage::disk('public')->url($path);
                
                $message->update(['media_url' => $mediaUrl]);
                unset($this->messages);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load media on-demand', ['messageId' => $messageId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Refresh unread counts and messages - called by polling.
     * This ensures all users see updated counts and new messages in real-time.
     */
    public function refreshUnreadCounts(): void
    {
        unset($this->channelUnreadCounts);
        unset($this->conversations);
        
        // Refresh channels to pick up connection status changes
        unset($this->channels);
        unset($this->isChannelConnected);
        
        // Also refresh messages for the active conversation so new incoming 
        // messages (including media) appear automatically without manual sync
        if ($this->selectedContactId !== null && $this->selectedChannelId !== null) {
            unset($this->messages);
        }
    }

    public function render()
    {
        return view('livewire.chat.index');
    }
}
