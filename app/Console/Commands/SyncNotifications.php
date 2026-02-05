<?php

namespace App\Console\Commands;

use App\Models\ChatNotification;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncNotifications extends Command
{
    protected $signature = 'notifications:sync {--clean : Clean duplicate notifications first}';
    protected $description = 'Sincroniza las notificaciones con los mensajes no leídos';

    public function handle(): int
    {
        $this->info('Sincronizando notificaciones...');

        if ($this->option('clean')) {
            $this->cleanDuplicates();
        }

        $this->syncWithMessages();

        $this->info('✓ Sincronización completada');
        return Command::SUCCESS;
    }

    private function cleanDuplicates(): void
    {
        $this->info('Limpiando notificaciones duplicadas...');

        // Delete notifications where message is already read
        $deleted = ChatNotification::whereHas('message', function ($q) {
            $q->where('is_read', true);
        })->delete();

        $this->info("  - Eliminadas {$deleted} notificaciones de mensajes ya leídos");

        // Keep only one notification per message (remove user-specific duplicates)
        $duplicates = DB::table('chat_notifications')
            ->select('message_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('message_id')
            ->groupBy('message_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deletedDuplicates = 0;
        foreach ($duplicates as $dup) {
            $deletedDuplicates += ChatNotification::where('message_id', $dup->message_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        $this->info("  - Eliminadas {$deletedDuplicates} notificaciones duplicadas");

        // Set user_id to null for remaining notifications (make them global)
        $updated = ChatNotification::whereNotNull('user_id')->update(['user_id' => null]);
        $this->info("  - Convertidas {$updated} notificaciones a globales");
    }

    private function syncWithMessages(): void
    {
        $this->info('Sincronizando con mensajes no leídos...');

        // Get all unread incoming messages that don't have a notification
        $unreadMessages = Message::where('direction', 'incoming')
            ->where('is_read', false)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('chat_notifications')
                  ->whereColumn('chat_notifications.message_id', 'messages.id');
            })
            ->with('contact')
            ->get();

        $created = 0;
        foreach ($unreadMessages as $message) {
            ChatNotification::create([
                'user_id' => null,
                'contact_id' => $message->contact_id,
                'channel_id' => $message->channel_id,
                'message_id' => $message->id,
                'type' => 'new_message',
                'title' => $message->contact?->display_name ?? 'Contacto',
                'body' => mb_substr($message->content ?? '', 0, 100),
                'is_read' => false,
            ]);
            $created++;
        }

        $this->info("  - Creadas {$created} notificaciones para mensajes sin notificación");

        // Mark notifications as read if their message is read
        $markedRead = ChatNotification::where('is_read', false)
            ->whereHas('message', function ($q) {
                $q->where('is_read', true);
            })
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $this->info("  - Marcadas {$markedRead} notificaciones como leídas");

        // Show final counts
        $totalUnread = ChatNotification::where('is_read', false)->count();
        $totalMessages = Message::where('direction', 'incoming')->where('is_read', false)->count();

        $this->info("  - Total notificaciones no leídas: {$totalUnread}");
        $this->info("  - Total mensajes no leídos: {$totalMessages}");
    }
}
