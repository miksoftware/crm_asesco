<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'icon',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public static function getActions(): array
    {
        return [
            'ver' => 'Ver',
            'crear' => 'Crear',
            'editar' => 'Editar',
            'eliminar' => 'Eliminar',
        ];
    }
}
