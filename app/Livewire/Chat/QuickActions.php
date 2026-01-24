<?php

namespace App\Livewire\Chat;

use App\Enums\ContactLabel;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\PaymentPromise;
use App\Services\MessageService;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Quick Actions Component for Collection Workflow
 * 
 * Provides quick action buttons for collection operations:
 * - Register payment promise
 * - Mark as paid
 * - Schedule follow-up
 * - Send reminder
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
 */
class QuickActions extends Component
{
    public Contact $contact;
    public int $channelId;

    // Modal states
    public bool $showPromiseModal = false;
    public bool $showFollowUpModal = false;

    // Promise form
    public ?string $promiseDate = null;
    public ?string $promiseAmount = null;
    public string $promiseNotes = '';

    // Follow-up form
    public ?string $followUpDate = null;
    public string $followUpNote = '';

    // Permission flags
    public bool $canSend = false;
    public bool $canManageLabels = false;

    public function mount(Contact $contact, int $channelId): void
    {
        $this->contact = $contact;
        $this->channelId = $channelId;

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $this->canSend = $isAdmin || $user->hasPermission('chats.enviar');
        $this->canManageLabels = $isAdmin || $user->hasPermission('chats.etiquetas');
    }

    /**
     * Open the promise modal.
     * Requirements: 7.2
     */
    public function openPromiseModal(): void
    {
        $this->resetPromiseForm();
        $this->showPromiseModal = true;
    }

    /**
     * Close the promise modal.
     */
    public function closePromiseModal(): void
    {
        $this->showPromiseModal = false;
        $this->resetPromiseForm();
    }

    /**
     * Reset promise form fields.
     */
    private function resetPromiseForm(): void
    {
        $this->promiseDate = null;
        $this->promiseAmount = null;
        $this->promiseNotes = '';
    }

    /**
     * Register a payment promise.
     * Requirements: 7.2
     */
    public function registerPromise(): void
    {
        $this->validate([
            'promiseDate' => 'required|date|after_or_equal:today',
            'promiseAmount' => 'required|numeric|min:0.01',
        ], [
            'promiseDate.required' => 'La fecha es requerida',
            'promiseDate.date' => 'La fecha no es válida',
            'promiseDate.after_or_equal' => 'La fecha debe ser hoy o posterior',
            'promiseAmount.required' => 'El monto es requerido',
            'promiseAmount.numeric' => 'El monto debe ser un número',
            'promiseAmount.min' => 'El monto debe ser mayor a 0',
        ]);

        PaymentPromise::create([
            'contact_id' => $this->contact->id,
            'user_id' => auth()->id(),
            'promised_date' => $this->promiseDate,
            'promised_amount' => $this->promiseAmount,
            'status' => 'pending',
            'notes' => trim($this->promiseNotes) ?: null,
        ]);

        // Add 'promise' label if not already present
        $this->addLabelToContact('promise');

        $this->closePromiseModal();
        $this->dispatch('refresh-contact-info');
        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Promesa de pago registrada');
    }

    /**
     * Mark contact as paid.
     * Requirements: 7.3
     */
    public function markAsPaid(): void
    {
        if (!$this->canManageLabels) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para gestionar etiquetas');
            return;
        }

        // Add 'paid' label
        $this->addLabelToContact('paid');

        // Update any pending payment promises to fulfilled
        $this->contact->paymentPromises()
            ->where('status', 'pending')
            ->update([
                'status' => 'fulfilled',
                'fulfilled_at' => now(),
            ]);

        $this->dispatch('refresh-contact-info');
        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Contacto marcado como pagado');
    }

    /**
     * Open the follow-up modal.
     * Requirements: 7.4
     */
    public function openFollowUpModal(): void
    {
        $this->resetFollowUpForm();
        $this->showFollowUpModal = true;
    }

    /**
     * Close the follow-up modal.
     */
    public function closeFollowUpModal(): void
    {
        $this->showFollowUpModal = false;
        $this->resetFollowUpForm();
    }

    /**
     * Reset follow-up form fields.
     */
    private function resetFollowUpForm(): void
    {
        $this->followUpDate = null;
        $this->followUpNote = '';
    }

    /**
     * Schedule a follow-up.
     * Requirements: 7.4
     */
    public function scheduleFollowUp(): void
    {
        $this->validate([
            'followUpDate' => 'required|date|after_or_equal:today',
        ], [
            'followUpDate.required' => 'La fecha es requerida',
            'followUpDate.date' => 'La fecha no es válida',
            'followUpDate.after_or_equal' => 'La fecha debe ser hoy o posterior',
        ]);

        FollowUp::create([
            'contact_id' => $this->contact->id,
            'user_id' => auth()->id(),
            'scheduled_date' => $this->followUpDate,
            'note' => trim($this->followUpNote) ?: null,
            'status' => 'pending',
        ]);

        $this->closeFollowUpModal();
        $this->dispatch('refresh-contact-info');
        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Seguimiento programado');
    }

    /**
     * Send a reminder message.
     * Requirements: 7.5
     */
    public function sendReminder(): void
    {
        if (!$this->canSend) {
            $this->dispatch('toast', type: 'error', message: 'No tienes permiso para enviar mensajes');
            return;
        }

        // Predefined reminder template
        $reminderMessage = "Hola {$this->contact->display_name}, le recordamos que tiene un pago pendiente. Por favor, comuníquese con nosotros para regularizar su situación. Gracias.";

        $messageService = app(MessageService::class);

        try {
            $message = $messageService->sendTextMessage(
                $this->channelId,
                $this->contact->phone_number,
                $reminderMessage
            );

            if ($message->status === 'failed') {
                $this->dispatch('toast', type: 'error', message: 'Error al enviar el recordatorio');
            } else {
                $this->dispatch('toast', type: 'success', message: 'Recordatorio enviado');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Mark conversation as unread.
     */
    public function markAsUnread(): void
    {
        // Mark last incoming message as unread
        \App\Models\Message::where('contact_id', $this->contact->id)
            ->where('channel_id', $this->channelId)
            ->where('direction', 'incoming')
            ->orderByDesc('sent_at')
            ->limit(1)
            ->update(['is_read' => false]);

        // Create notification for this conversation
        $notificationService = app(\App\Services\NotificationService::class);
        $lastMessage = \App\Models\Message::where('contact_id', $this->contact->id)
            ->where('channel_id', $this->channelId)
            ->where('direction', 'incoming')
            ->orderByDesc('sent_at')
            ->first();

        if ($lastMessage) {
            $notificationService->createMessageNotification($lastMessage);
        }

        $this->dispatch('notifications-updated');
        $this->dispatch('contact-updated');
        $this->dispatch('toast', type: 'success', message: 'Chat marcado como no leído');
    }

    /**
     * Add a label to the contact if not already present.
     */
    private function addLabelToContact(string $labelValue): void
    {
        $labels = $this->contact->labels ?? [];
        
        if (!in_array($labelValue, $labels)) {
            $labels[] = $labelValue;
            $this->contact->update(['labels' => $labels]);
            $this->contact->refresh();
        }
    }

    #[On('refresh-quick-actions')]
    public function refreshContact(): void
    {
        $this->contact->refresh();
    }

    public function render()
    {
        return view('livewire.chat.quick-actions');
    }
}
