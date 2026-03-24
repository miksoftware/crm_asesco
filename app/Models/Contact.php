<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    use HasFactory;
    protected $fillable = [
        'channel_id',
        'assigned_user_id',
        'phone_number',
        'remote_jid',
        'is_group',
        'group_jid',
        'is_lid',
        'lid_jid',
        'name',
        'push_name',
        'profile_picture',
        'notes',
        'labels',
        'metadata',
        'last_message_at',
    ];

    protected $casts = [
        'labels' => 'array',
        'metadata' => 'array',
        'is_group' => 'boolean',
        'is_lid' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the channel that owns the contact.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the assigned user for this contact.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get the messages for the contact.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the latest message for the contact (eager-loadable).
     * Uses latestOfMany to avoid N+1 queries.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    /**
     * Get the payment promises for the contact.
     */
    public function paymentPromises(): HasMany
    {
        return $this->hasMany(PaymentPromise::class);
    }

    /**
     * Get the follow-ups for the contact.
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Get the labels for this contact (new system).
     */
    public function labelRelations(): BelongsToMany
    {
        return $this->belongsToMany(Label::class)->withTimestamps();
    }

    /**
     * Get the chat transfers for this contact.
     */
    public function chatTransfers(): HasMany
    {
        return $this->hasMany(ChatTransfer::class);
    }

    /**
     * Get the display name for the contact.
     * Prioridad: nombre personalizado > push_name > numero de telefono.
     * Si push_name es generico (ej: "Voce", "You"), muestra el numero.
     * Para leads LID sin nombre, muestra indicador de lead temporal.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->push_name) {
            return $this->push_name;
        }

        if ($this->is_lid && !$this->isResolvedLid()) {
            return '📱 Lead #' . substr($this->phone_number, -6);
        }

        return $this->phone_number ?? 'Sin nombre';
    }

    /**
     * Verifica si este contacto LID ya fue resuelto a un número real.
     */
    public function isResolvedLid(): bool
    {
        if (!$this->is_lid) {
            return false;
        }
        // Si tiene lid_jid y el phone_number es diferente al lid, está resuelto
        return $this->lid_jid && $this->phone_number !== $this->lid_jid;
    }

    /**
     * Detecta si este contacto necesita resolución manual de número.
     * Retorna true si:
     * - Tiene is_lid=true y no está resuelto
     * - O su phone_number no es un número colombiano válido (57 + 10 dígitos)
     *   y no es un grupo
     */
    public function needsNumberResolution(): bool
    {
        // Grupos no necesitan resolución
        if ($this->is_group) {
            return false;
        }

        // Si está marcado como LID y no resuelto
        if ($this->is_lid && !$this->isResolvedLid()) {
            return true;
        }

        // Detectar por formato de número: si no es un teléfono real válido
        // Números reales: 10-15 dígitos empezando con código de país válido
        // LIDs: suelen ser 13+ dígitos que no corresponden a ningún país
        $phone = $this->phone_number;
        if (!$phone) {
            return false;
        }

        // Si el remote_jid contiene @lid, necesita resolución
        if ($this->remote_jid && str_contains($this->remote_jid, '@lid')) {
            return true;
        }

        // Número colombiano válido: 57 + 10 dígitos = 12 dígitos
        if (preg_match('/^57\d{10}$/', $phone)) {
            return false;
        }

        // Otros números internacionales válidos: 10-15 dígitos con código de país conocido
        // Si tiene más de 14 dígitos, probablemente es un LID
        if (strlen($phone) > 14) {
            return true;
        }

        // Si no empieza con un código de país razonable (1-9 seguido de dígitos, 10-14 dígitos total)
        if (!preg_match('/^[1-9]\d{9,13}$/', $phone)) {
            return true;
        }

        return false;
    }

    /**
     * Resuelve un contacto LID: actualiza su número real, fusiona historial
     * si ya existe un contacto con ese número, y actualiza lid_mappings.
     */
    public function resolveLid(string $realPhoneNumber, ?int $channelId = null): self
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $realPhoneNumber);

        if (empty($cleanPhone) || strlen($cleanPhone) < 8) {
            throw new \InvalidArgumentException("Número de teléfono inválido: {$realPhoneNumber}");
        }

        $channelId = $channelId ?? $this->channel_id;

        // Guardar mapeo LID → teléfono real
        LidMapping::createMapping($this->lid_jid ?? $this->phone_number, $cleanPhone, null, $channelId);

        // Buscar si ya existe un contacto con el número real en este canal
        $existingContact = self::where('channel_id', $channelId)
            ->where('phone_number', $cleanPhone)
            ->where('id', '!=', $this->id)
            ->first();

        if ($existingContact) {
            // Fusionar: mover mensajes del LID al contacto real
            $this->messages()->update(['contact_id' => $existingContact->id]);
            $this->labelRelations()->detach();

            // Actualizar last_message_at del contacto destino
            $latestMsg = $existingContact->messages()->max('sent_at');
            if ($latestMsg) {
                $existingContact->update(['last_message_at' => $latestMsg]);
            }

            // Eliminar el contacto LID
            $this->delete();

            return $existingContact;
        }

        // No existe contacto real — actualizar este contacto
        $this->update([
            'phone_number' => $cleanPhone,
            'remote_jid' => $cleanPhone . '@s.whatsapp.net',
            'is_lid' => false,
        ]);

        return $this;
    }

    /**
     * Get the count of unread incoming messages.
     * If 'unread_count' was loaded via addSelect subquery, use that value.
     * Otherwise falls back to a query (avoid in loops).
     */
    public function getUnreadCountAttribute(): int
    {
        // If loaded via subquery in conversations(), use cached value
        if (array_key_exists('unread_count', $this->attributes)) {
            return (int) $this->attributes['unread_count'];
        }

        return $this->messages()
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get the last message for the contact.
     * Uses eager-loaded latestMessage relationship when available.
     */
    public function getLastMessageAttribute(): ?Message
    {
        // Use eager-loaded latestMessage relationship (no extra query)
        if ($this->relationLoaded('latestMessage')) {
            return $this->latestMessage;
        }

        // Use manually set messages relation
        if ($this->relationLoaded('messages')) {
            return $this->messages->first();
        }

        // Fallback: direct query (avoid in loops)
        return $this->messages()
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
