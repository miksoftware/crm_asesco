<?php

namespace App\Livewire\Chat;

use App\Enums\ContactLabel;
use App\Models\Contact;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Contact Info Panel Component
 * 
 * Displays contact information including name, phone, labels, notes,
 * conversation history summary, payment promises, and follow-ups.
 * Allows editing of contact name and notes.
 * 
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5
 */
class ContactInfo extends Component
{
    public Contact $contact;
    public bool $editing = false;
    public string $editName = '';
    public string $editNotes = '';

    // Permission flag
    public bool $canManageLabels = false;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
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
     * Requirements: 8.3
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
     * Add a label to the contact.
     * Requirements: 6.2, 6.4
     */
    public function addLabel(string $labelValue): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para gestionar etiquetas');
            return;
        }

        // Validate label value
        $validLabel = ContactLabel::tryFrom($labelValue);
        if (!$validLabel) {
            $this->dispatch('toast', type: 'error', message: 'Etiqueta no válida');
            return;
        }

        $labels = $this->contact->labels ?? [];
        
        // Don't add if already exists
        if (in_array($labelValue, $labels)) {
            return;
        }

        $labels[] = $labelValue;
        $this->contact->update(['labels' => $labels]);
        $this->contact->refresh();

        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Etiqueta agregada');
    }

    /**
     * Remove a label from the contact.
     * Requirements: 6.5
     */
    public function removeLabel(string $labelValue): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para gestionar etiquetas');
            return;
        }

        $labels = $this->contact->labels ?? [];
        
        // Remove the label
        $labels = array_values(array_filter($labels, fn($l) => $l !== $labelValue));
        $this->contact->update(['labels' => $labels]);
        $this->contact->refresh();

        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Etiqueta eliminada');
    }

    /**
     * Get available labels for the dropdown.
     */
    public function getAvailableLabelsProperty(): array
    {
        return ContactLabel::cases();
    }

    /**
     * Get total message count for the contact.
     * Requirements: 8.2
     */
    public function getTotalMessagesProperty(): int
    {
        return $this->contact->messages()->count();
    }

    /**
     * Get first contact date.
     * Requirements: 8.2
     */
    public function getFirstContactDateProperty(): ?string
    {
        $firstMessage = $this->contact->messages()
            ->orderBy('sent_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();

        if ($firstMessage) {
            return $firstMessage->sent_at?->format('d/m/Y') ?? $firstMessage->created_at->format('d/m/Y');
        }

        return $this->contact->created_at->format('d/m/Y');
    }

    /**
     * Get payment promises for the contact.
     * Requirements: 8.5
     */
    public function getPaymentPromisesProperty()
    {
        return $this->contact->paymentPromises()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get pending follow-ups for the contact.
     * Requirements: 8.4
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
     * Requirements: 8.4
     */
    public function getAllFollowUpsProperty()
    {
        return $this->contact->followUps()
            ->orderByDesc('scheduled_date')
            ->get();
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
