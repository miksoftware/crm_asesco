<?php

namespace App\Livewire\Chat;

use App\Models\Contact;
use App\Models\Label;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Contact Info Panel Component
 * 
 * Displays contact information including name, phone, labels, notes,
 * conversation history summary, payment promises, and follow-ups.
 * Allows editing of contact name and notes.
 */
class ContactInfo extends Component
{
    public Contact $contact;
    public ?int $channelId = null;
    public bool $editing = false;
    public string $editName = '';
    public string $editNotes = '';

    // Permission flag
    public bool $canManageLabels = false;

    public function mount(Contact $contact, ?int $channelId = null): void
    {
        $this->contact = $contact;
        $this->channelId = $channelId ?? $contact->channel_id;
        $this->editName = $contact->name ?? '';
        $this->editNotes = $contact->notes ?? '';

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $this->canManageLabels = $isAdmin || $user->hasPermission('chats.etiquetas');
    }

    /**
     * Start editing mode.
     */
    public function startEditing(): void
    {
        $this->editing = true;
        $this->editName = $this->contact->name ?? '';
        $this->editNotes = $this->contact->notes ?? '';
    }

    /**
     * Cancel editing mode.
     */
    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->editName = $this->contact->name ?? '';
        $this->editNotes = $this->contact->notes ?? '';
    }

    /**
     * Update contact name and notes.
     */
    public function updateContact(): void
    {
        $name = trim($this->editName);
        $notes = trim($this->editNotes);

        $this->contact->update([
            'name' => $name !== '' ? $name : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $this->contact->refresh();
        $this->editing = false;

        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Contacto actualizado');
    }

    /**
     * Add a label to the contact using the labels table.
     */
    public function addLabel(int $labelId): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para gestionar etiquetas');
            return;
        }

        $label = Label::find($labelId);
        if (!$label) {
            $this->dispatch('toast', type: 'error', message: 'Etiqueta no encontrada');
            return;
        }

        // Check if already attached
        if ($this->contact->labelRelations()->where('label_id', $labelId)->exists()) {
            return;
        }

        $this->contact->labelRelations()->attach($labelId);
        $this->contact->refresh();

        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Etiqueta agregada');
    }

    /**
     * Remove a label from the contact.
     */
    public function removeLabel(int $labelId): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para gestionar etiquetas');
            return;
        }

        $this->contact->labelRelations()->detach($labelId);
        $this->contact->refresh();

        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Etiqueta eliminada');
    }

    /**
     * Get available labels from the database.
     */
    public function getAvailableLabelsProperty()
    {
        return Label::orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get labels not yet assigned to this contact.
     */
    public function getUnassignedLabelsProperty()
    {
        $assignedIds = $this->contact->labelRelations()->pluck('labels.id')->toArray();
        return Label::whereNotIn('id', $assignedIds)
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get total message count for the contact.
     */
    public function getTotalMessagesProperty(): int
    {
        return $this->contact->messages()->count();
    }

    /**
     * Get first contact date.
     */
    public function getFirstContactDateProperty(): ?string
    {
        $firstMessage = $this->contact->messages()
            ->orderBy('id', 'asc')
            ->first();

        if ($firstMessage) {
            return $firstMessage->sent_at?->format('d/m/Y') ?? $firstMessage->created_at->format('d/m/Y');
        }

        return $this->contact->created_at->format('d/m/Y');
    }

    /**
     * Get payment promises for the contact.
     */
    public function getPaymentPromisesProperty()
    {
        return $this->contact->paymentPromises()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get pending follow-ups for the contact.
     */
    public function getPendingFollowUpsProperty()
    {
        return $this->contact->followUps()
            ->where('status', 'pending')
            ->orderBy('scheduled_date', 'asc')
            ->get();
    }

    /**
     * Get all follow-ups for the contact.
     */
    public function getAllFollowUpsProperty()
    {
        return $this->contact->followUps()
            ->orderByDesc('scheduled_date')
            ->get();
    }

    /**
     * Get users assigned to this channel.
     */
    public function getChannelUsersProperty()
    {
        $channel = \App\Models\Channel::find($this->channelId);
        if (!$channel) {
            return collect();
        }
        
        return $channel->users()->orderBy('name')->get();
    }

    /**
     * Assign a user to this contact.
     */
    public function assignUser(?int $userId): void
    {
        $this->contact->update([
            'assigned_user_id' => $userId,
        ]);

        $this->contact->refresh();
        $this->dispatch('contact-updated');
        
        if ($userId) {
            $user = \App\Models\User::find($userId);
            $this->dispatch('toast', type: 'success', message: 'Asignado a ' . ($user?->name ?? 'usuario'));
        } else {
            $this->dispatch('toast', type: 'success', message: 'Asignación removida');
        }
    }

    #[On('refresh-contact-info')]
    public function refreshContact(): void
    {
        $this->contact->refresh();
    }

    public function render()
    {
        return view('livewire.chat.contact-info');
    }
}
