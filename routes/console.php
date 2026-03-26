<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sincronizar chats cada 2 minutos (respaldo si el webhook falla)
Schedule::command('chats:sync --limit=50')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sincronizar notificaciones cada 10 minutos (limpia huérfanas y sincroniza con mensajes)
Schedule::command('notifications:sync')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Arreglar mensajes no leídos que ya fueron respondidos (cada hora)
Schedule::command('messages:fix-unread')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Enviar recordatorios y mensajes automáticos de promesas de pago (cada minuto)
Schedule::command('promises:send')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
