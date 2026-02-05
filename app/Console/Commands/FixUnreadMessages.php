<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixUnreadMessages extends Command
{
    protected $signature = 'messages:fix-unread';
    protected $description = 'Marca como leídos los mensajes entrantes de conversaciones que ya fueron respondidas';

    public function handle(): int
    {
        $this->info('Buscando mensajes no leídos en conversaciones respondidas...');

        // Find contacts that have:
        // 1. Unread incoming messages
        // 2. At least one outgoing message AFTER the unread incoming message
        $contactsWithUnread = DB::table('messages as m1')
            ->select('m1.contact_id', 'm1.channel_id')
            ->where('m1.direction', 'incoming')
            ->where('m1.is_read', false)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('messages as m2')
                    ->whereColumn('m2.contact_id', 'm1.contact_id')
                    ->whereColumn('m2.channel_id', 'm1.channel_id')
                    ->where('m2.direction', 'outgoing')
                    ->whereColumn('m2.sent_at', '>', 'm1.sent_at');
            })
            ->distinct()
            ->get();

        $totalFixed = 0;

        foreach ($contactsWithUnread as $row) {
            // Get the last outgoing message time for this contact
            $lastOutgoing = Message::where('contact_id', $row->contact_id)
                ->where('channel_id', $row->channel_id)
                ->where('direction', 'outgoing')
                ->max('sent_at');

            if ($lastOutgoing) {
                // Mark all incoming messages before the last outgoing as read
                $fixed = Message::where('contact_id', $row->contact_id)
                    ->where('channel_id', $row->channel_id)
                    ->where('direction', 'incoming')
                    ->where('is_read', false)
                    ->where('sent_at', '<', $lastOutgoing)
                    ->update(['is_read' => true]);

                $totalFixed += $fixed;
            }
        }

        $this->info("✓ Se marcaron {$totalFixed} mensajes como leídos");

        // Also show remaining unread count
        $remaining = Message::where('direction', 'incoming')
            ->where('is_read', false)
            ->count();

        $this->info("  Mensajes no leídos restantes: {$remaining}");

        return Command::SUCCESS;
    }
}
