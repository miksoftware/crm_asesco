<?php

namespace App\Livewire\Help;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Manual Técnico')]
class TechnicalManual extends Component
{
    public string $activeSection = 'overview';

    public array $sections = [
        'overview' => 'Descripción General',
        'stack' => 'Stack Tecnológico',
        'requirements' => 'Requisitos del Sistema',
        'installation' => 'Instalación',
        'database' => 'Base de Datos',
        'seeders' => 'Seeders',
        'permissions' => 'Sistema de Permisos',
        'evolution' => 'Evolution API',
        'production' => 'Despliegue en Producción',
        'commands' => 'Comandos Útiles',
    ];

    public function setSection(string $section): void
    {
        $this->activeSection = $section;
    }

    public function render()
    {
        return view('livewire.help.technical-manual');
    }
}
