<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'from_channel_id',
        'to_channel_id',
        'from_user_id',
        'to_user_id',
        'internal_note',
        'status',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function fromChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'from_channel_id');
    }

    public function toChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'to_channel_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'transferred_at' => now(),
        ]);

        // Update contact's assigned user and channel
        $this->contact->update([
            'assigned_user_id' => $this->to_user_id,
            'channel_id' => $this->to_channel_id,
        ]);
    }

    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
    }
}
