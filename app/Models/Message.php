<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;
    protected $fillable = [
        'contact_id',
        'channel_id',
        'user_id',
        'message_id',
        'direction',
        'type',
        'content',
        'media_url',
        'media_mime_type',
        'sender_name',
        'sender_phone',
        'status',
        'is_read',
        'metadata',
        'sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the contact that owns the message.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the channel that owns the message.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the user who sent this message (for outgoing messages).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
