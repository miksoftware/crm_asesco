<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPromise extends Model
{
    protected $fillable = [
        'contact_id',
        'user_id',
        'promised_date',
        'promised_amount',
        'status',
        'notes',
        'fulfilled_at',
    ];

    protected $casts = [
        'promised_date' => 'date',
        'promised_amount' => 'decimal:2',
        'fulfilled_at' => 'datetime',
    ];

    /**
     * Get the contact that owns the payment promise.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user that created the payment promise.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
