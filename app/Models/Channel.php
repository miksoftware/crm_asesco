<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'instance_name',
        'phone_number',
        'token',
        'status',
        'integration',
        'qr_code',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Get the contacts for the channel.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get the messages for the channel.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'connected' => '#10b981',
            'connecting' => '#f59e0b',
            'qr_code' => '#3b82f6',
            default => '#ef4444',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'connected' => 'Conectado',
            'connecting' => 'Conectando',
            'qr_code' => 'Escanear QR',
            default => 'Desconectado',
        };
    }
}
