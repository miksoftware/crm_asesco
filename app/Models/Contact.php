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
     * Returns name, push_name, or phone_number as fallback.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->push_name ?? $this->phone_number;
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
