<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Channel extends Model
{
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
