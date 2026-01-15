<?php

namespace App\Livewire\Chat;

use App\Enums\ContactLabel;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Services\MessageService;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;

#[Layout('layouts.app')]
#[Title('Chat')]
class Index extends Component
{
    #[Url(history: true)]
    public ?int $selectedChannelId = null;

    #[Url(history: true)]
    public ?int $selectedContactId = null;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public ?string $labelFilter = null;

    public string $messageText = '';

    public int $messagesLimit = 50;
    public ?int $oldestMessageId = null;
    public bool $hasMoreMessages = true;

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

        // Base query: only active and connected channels
        $baseConditions = function ($query) {
            $query->where('is_active', true)
                  ->where('status', 'connected');
        };

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

        // Apply label filter
        if (!empty($this->labelFilter)) {
            $query->whereJsonContains('labels', $this->labelFilter);
        }

        // Get contacts with last message info for sorting
        $contacts = $query->with(['messages' => function ($q) {
            $q->orderByDesc('sent_at')->orderByDesc('created_at')->limit(1);
        }])->get();

        // Sort by last message timestamp (descending)
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

        // Track oldest message for pagination
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

        return Contact::with(['paymentPromises', 'followUps'])
            ->find($this->selectedContactId);
    }

    #[Computed]
    public function availableLabels(): array
    {
        return ContactLabel::cases();
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

        // Mark messages as read
        $this->markAsRead($contactId);
    }


    public function sendMessage(): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        $text = trim($this->messageText);

        if (empty($text)) {
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

            // Refresh messages
            unset($this->messages);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    public function retryMessage(int $messageId): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        $message = Message::find($messageId);
        if (!$message || $message->status !== 'failed') {
            return;
        }

        $contact = Contact::find($message->contact_id);
        if (!$contact) {
            return;
        }

        $messageService = app(MessageService::class);

        try {
            // Update status to pending
            $message->update(['status' => 'pending']);

            // Retry sending via Evolution API
            $channel = Channel::find($message->channel_id);
            $evolutionApi = app(\App\Services\EvolutionApiService::class);
            
            $response = $evolutionApi->sendTextMessage(
                $channel->instance_name,
                $contact->phone_number,
                $message->content
            );

            if ($response['success']) {
                $newMessageId = $response['data']['key']['id'] ?? null;
                $message->update([
                    'message_id' => $newMessageId,
                    'status' => 'sent',
                ]);
                $this->dispatch('toast', type: 'success', message: 'Mensaje reenviado');
            } else {
                $message->update(['status' => 'failed']);
                $this->dispatch('toast', type: 'error', message: 'Error al reenviar el mensaje');
            }
        } catch (\Exception $e) {
            $message->update(['status' => 'failed']);
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }

        // Refresh messages
        unset($this->messages);
    }

    public function loadMoreMessages(): void
    {
        if (!$this->hasMoreMessages || $this->oldestMessageId === null) {
            return;
        }

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

        // Update oldest message ID
        $this->oldestMessageId = $olderMessages->first()->id;

        // Increase limit to include older messages
        $this->messagesLimit += $olderMessages->count();

        // Clear computed cache
        unset($this->messages);
    }

    public function markAsRead(int $contactId): void
    {
        if ($this->selectedChannelId === null) {
            return;
        }

        $messageService = app(MessageService::class);
        $messageService->markMessagesAsRead($contactId, $this->selectedChannelId);

        // Also mark notifications as read
        $notificationService = app(NotificationService::class);
        $notificationService->markConversationAsRead(
            auth()->id(),
            $contactId,
            $this->selectedChannelId
        );

        // Dispatch event to update notification badge in real-time
        $this->dispatch('notifications-updated');

        // Refresh conversations to update unread counts
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
        // Refresh conversations and messages if relevant
        if (isset($data['channel_id']) && $data['channel_id'] == $this->selectedChannelId) {
            unset($this->conversations);

            if (isset($data['contact_id']) && $data['contact_id'] == $this->selectedContactId) {
                unset($this->messages);
                // Auto-mark as read if viewing this conversation
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
     * Add a label to the selected contact.
     */
    public function addLabel(string $labelValue): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para gestionar etiquetas');
            return;
        }

        if ($this->selectedContactId === null) {
            return;
        }

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) {
            return;
        }

        // Validate label value
        $validLabel = ContactLabel::tryFrom($labelValue);
        if (!$validLabel) {
            $this->dispatch('toast', type: 'error', message: 'Etiqueta no válida');
            return;
        }

        $labels = $contact->labels ?? [];
        
        // Don't add if already exists
        if (in_array($labelValue, $labels)) {
            return;
        }

        $labels[] = $labelValue;
        $contact->update(['labels' => $labels]);

        // Refresh UI
        unset($this->selectedContact);
        unset($this->conversations);

        $this->dispatch('toast', type: 'success', message: 'Etiqueta agregada');
    }

    /**
     * Remove a label from the selected contact.
     */
    public function removeLabel(string $labelValue): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para gestionar etiquetas');
            return;
        }

        if ($this->selectedContactId === null) {
            return;
        }

        $contact = Contact::find($this->selectedContactId);
        if (!$contact) {
            return;
        }

        $labels = $contact->labels ?? [];
        
        // Remove the label
        $labels = array_values(array_filter($labels, fn($l) => $l !== $labelValue));
        $contact->update(['labels' => $labels]);

        // Refresh UI
        unset($this->selectedContact);
        unset($this->conversations);

        $this->dispatch('toast', type: 'success', message: 'Etiqueta eliminada');
    }

    public function render()
    {
        return view('livewire.chat.index');
    }
}
