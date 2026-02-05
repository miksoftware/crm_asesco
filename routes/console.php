<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sincronizar chats cada 5 minutos
Schedule::command('chats:sync --limit=50')
    ->everyFiveMinutes()
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
