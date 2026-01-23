<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for storing WhatsApp LID to phone number mappings.
 * 
 * WhatsApp uses LIDs (Link IDs) as internal privacy identifiers.
 * These cannot be used to send messages - we need the real phone number.
 * This table captures mappings when we see the same message with both formats.
 */
class LidMapping extends Model
{
    protected $fillable = [
        'lid',
        'phone_number',
        'message_id',
        'channel_id',
    ];

    /**
     * Get the channel for this mapping.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Find phone number by LID.
     */
    public static function findPhoneByLid(string $lid): ?string
    {
        // Clean the LID - remove @lid suffix if present
        $cleanLid = preg_replace('/@lid$/', '', $lid);
        // Also remove any :XX suffix
        $cleanLid = explode(':', $cleanLid)[0];
        
        $mapping = self::where('lid', $cleanLid)->first();
        return $mapping?->phone_number;
    }

    /**
     * Create or update a mapping.
     */
    public static function createMapping(string $lid, string $phoneNumber, ?string $messageId = null, ?int $channelId = null): self
    {
        // Clean the LID
        $cleanLid = preg_replace('/@lid$/', '', $lid);
        $cleanLid = explode(':', $cleanLid)[0];
        
        // Clean the phone number
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        $cleanPhone = preg_replace('/@s\.whatsapp\.net$/', '', $cleanPhone);
        
        return self::updateOrCreate(
            ['lid' => $cleanLid],
            [
                'phone_number' => $cleanPhone,
                'message_id' => $messageId,
                'channel_id' => $channelId,
            ]
        );
    }

    /**
     * Check if a JID is a LID format.
     */
    public static function isLidFormat(string $jid): bool
    {
        return str_contains($jid, '@lid') || 
               (str_contains($jid, ':') && !str_contains($jid, '@s.whatsapp.net'));
    }
}
