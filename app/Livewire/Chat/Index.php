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

        if ($user->hasRole('admin')) {
            return Channel::where('is_active', true)
                ->where('status', 'connected')
                ->orderBy('name')
                ->get();
        }

        return $user->channels()
            ->where('is_active', true)
            ->where('status', 'connected')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function conversations(): Collection
    {
        if ($this->selectedChannelId === null) {
            return collect();
        }

        $query = Contact::where('channel_id', $this->selectedChannelId)
            ->whereHas('messages');

        // Apply search filter
        if (!empty($this->search)) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('push_name', 'like', $searchTerm)
                    ->orWhere('phone_number', 'like', $searchTerm);
            });
        }

        // Apply label filter (using new Label model)
        if (!empty($this->labelFilter)) {
            $query->whereHas('labelRelations', function ($q) {
                $q->where('labels.id', $this->labelFilter);
            });
        }

        $contacts = $query->with(['messages' => function ($q) {
            $q->orderByDesc('sent_at')->orderByDesc('created_at')->limit(1);
        }])->get();

        return $contacts->sortByDesc(function ($contact) {
            $lastMessage = $contact->messages->first();
            return $lastMessage ? $lastMessage->sent_at : $contact->created_at;
        })->values();
    }

    #[Computed]
    public function messages(): Collection
    {
        if ($this->selectedContactId === null || $this->selectedChannelId === null) {
            return collect();
        }

        $query = Message::where('contact_id', $this->selectedContactId)
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
        return User::where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allChannels(): Collection
    {
        return Channel::where('is_active', true)
            ->where('status', 'connected')
            ->orderBy('name')
            ->get();
    }

    public function selectChannel(int $channelId): void
    {
        $this->selectedChannelId = $channelId;
        $this->selectedContactId = null;
        $this->resetMessages();
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

        // If there's a media file, send it
        if ($this->mediaFile) {
            $this->sendMediaMessage($contact, $text);
            return;
        }

        // Send text message
        $messageService = app(MessageService::class);

        try {
            $message = $messageService->sendTextMessage(
                $this->selectedChannelId,
                $contact->phone_number,
                $text
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
        $recipient = $contact->remote_jid ?? $contact->phone_number;

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
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'direction' => 'outgoing',
                    'type' => $type,
                    'content' => $caption ?: $fileName,
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $mimeType,
                    'status' => 'sent',
                    'is_read' => true,
                    'sent_at' => now(),
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
        $recipient = $contact->remote_jid ?? $contact->phone_number;

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
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'direction' => 'outgoing',
                    'type' => 'audio',
                    'content' => '[Audio]',
                    'media_url' => $mediaUrl,
                    'media_mime_type' => 'audio/ogg; codecs=opus',
                    'status' => 'sent',
                    'is_read' => true,
                    'sent_at' => now(),
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
                $contact->remote_jid ?? $contact->phone_number,
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

        $this->dispatch('notifications-updated');
        unset($this->conversations);
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

    public function render()
    {
        return view('livewire.chat.index');
    }
}
