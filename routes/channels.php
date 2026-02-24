<?php

use App\Models\Channel;
use App\Models\Contact;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canales de Broadcasting
|--------------------------------------------------------------------------
|
| Autorización de canales privados para el sistema de chat en tiempo real.
| Solo usuarios autenticados con acceso al canal de WhatsApp pueden suscribirse.
|
*/

/**
 * Canal privado por conversación (contacto).
 * Autoriza si el usuario tiene acceso al canal de WhatsApp del contacto.
 */
Broadcast::channel('chat.contact.{contactId}', function ($user, $contactId) {
    $contact = Contact::find($contactId);
    if (!$contact) {
        return false;
    }

    // Admin tiene acceso total
    if ($user->hasRole('admin')) {
        return true;
    }

    // Verificar que el usuario tiene asignado el canal de WhatsApp
    return $user->channels()->where('channels.id', $contact->channel_id)->exists();
});

/**
 * Canal privado por canal de WhatsApp.
 * Autoriza si el usuario tiene asignado ese canal.
 */
Broadcast::channel('chat.channel.{channelId}', function ($user, $channelId) {
    // Admin tiene acceso total
    if ($user->hasRole('admin')) {
        return true;
    }

    return $user->channels()->where('channels.id', $channelId)->exists();
});
