<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;
    protected $fillable = [
        'channel_id',
        'phone_number',
        'remote_jid',
        'name',
        'push_name',
        'profile_picture',
        'notes',
        'labels',
        'metadata',
    ];

    protected $casts = [
        'labels' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the channel that owns the contact.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the messages for the contact.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
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
     * Get the display name for the contact.
     * Returns name, push_name, or phone_number as fallback.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->push_name ?? $this->phone_number;
    }

    /**
     * Get the count of unread incoming messages.
     */
    public function getUnreadCountAttribute(): int
    {
        return $this->messages()
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get the last message for the contact.
     */
    public function getLastMessageAttribute(): ?Message
    {
        return $this->messages()
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
