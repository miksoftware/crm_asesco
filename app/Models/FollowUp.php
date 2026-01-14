<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    protected $fillable = [
        'contact_id',
        'user_id',
        'scheduled_date',
        'note',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the contact that owns the follow-up.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user that created the follow-up.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
