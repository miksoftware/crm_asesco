<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'contact_id',
        'channel_id',
        'user_id',
        'downloaded_by',
        'phone_number',
        'client_name',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'status',
        'expires_at',
        'uploaded_at',
        'downloaded_at',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function downloader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'downloaded_by');
    }

    /**
     * Generar un token único para el link.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Verifica si el token ha expirado.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Verifica si está disponible para subir.
     */
    public function canUpload(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'uploaded' => 'Subido',
            'downloaded' => 'Descargado',
            'expired' => 'Expirado',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'uploaded' => 'blue',
            'downloaded' => 'green',
            'expired' => 'gray',
            default => 'gray',
        };
    }
}
