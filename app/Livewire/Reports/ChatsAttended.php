<?php

namespace App\Livewire\Reports;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Chats Atendidos')]
class ChatsAttended extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';
    
    #[Url]
    public string $dateTo = '';
    
    #[Url]
    public ?int $userId = null;
    
    #[Url]
    public ?int $channelId = null;
    
    #[Url]
    public string $groupBy = 'day'; // day, week, month
    
    #[Url]
    public string $messageDirection = 'all'; // all, incoming, outgoing

    #[Url]
    public int $perPage = 25;

    public function mount(): void
    {
        // Default to last 30 days
        if (empty($this->dateFrom)) {
            $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        }
        if (empty($this->dateTo)) {
            $this->dateTo = now()->format('Y-m-d');
        }
    }

    #[Computed]
    public function users(): Collection
    {
        return User::orderBy('name')->get();
    }

    #[Computed]
    public function channels(): Collection
    {
        return Channel::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function totalMessages(): int
    {
        return $this->getBaseQuery()->count();
    }

    #[Computed]
    public function totalConversations(): int
    {
        return $this->getBaseQuery()->distinct('contact_id')->count('contact_id');
    }

    #[Computed]
    public function totalIncoming(): int
    {
        return $this->getBaseQuery()->where('direction', 'incoming')->count();
    }

    #[Computed]
    public function totalOutgoing(): int
    {
        return $this->getBaseQuery()->where('direction', 'outgoing')->count();
    }

    #[Computed]
    public function avgMessagesPerDay(): float
    {
        $days = Carbon::parse($this->dateFrom)->diffInDays(Carbon::parse($this->dateTo)) + 1;
        return $days > 0 ? round($this->totalMessages / $days, 1) : 0;
    }

    #[Computed]
    public function avgResponseTime(): string
    {
        // Calculate average time between incoming and outgoing messages
        // This is a simplified calculation
        return 'N/A';
    }

    #[Computed]
    public function messagesByDate(): array
    {
        $format = match($this->groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $results = $this->getBaseQuery()
            ->select(DB::raw("DATE_FORMAT(sent_at, '{$format}') as date_group"), DB::raw('COUNT(*) as total'))
            ->groupBy('date_group')
            ->orderBy('date_group')
            ->get();

        $labels = [];
        $data = [];

        foreach ($results as $row) {
            $labels[] = $this->formatDateLabel($row->date_group);
            $data[] = $row->total;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    #[Computed]
    public function messagesByUser(): array
    {
        $results = Message::query()
            ->join('users', 'messages.user_id', '=', 'users.id')
            ->whereBetween('messages.sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
            ->where('messages.direction', 'outgoing')
            ->when($this->channelId, fn($q) => $q->where('messages.channel_id', $this->channelId))
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return $results->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'total' => $r->total,
            'color' => $this->getUserColor($r->id),
        ])->toArray();
    }

    #[Computed]
    public function messagesByChannel(): array
    {
        $results = Message::query()
            ->join('channels', 'messages.channel_id', '=', 'channels.id')
            ->whereBetween('messages.sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
            ->when($this->userId, fn($q) => $q->where('messages.user_id', $this->userId))
            ->when($this->messageDirection !== 'all', fn($q) => $q->where('messages.direction', $this->messageDirection))
            ->select('channels.id', 'channels.name', DB::raw('COUNT(*) as total'))
            ->groupBy('channels.id', 'channels.name')
            ->orderByDesc('total')
            ->get();

        return $results->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'total' => $r->total,
        ])->toArray();
    }

    #[Computed]
    public function messagesByHour(): array
    {
        $results = $this->getBaseQuery()
            ->select(DB::raw('HOUR(sent_at) as hour'), DB::raw('COUNT(*) as total'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 24; $i++) {
            $labels[] = sprintf('%02d:00', $i);
            $data[] = $results->get($i)?->total ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    #[Computed]
    public function messagesByDayOfWeek(): array
    {
        $results = $this->getBaseQuery()
            ->select(DB::raw('DAYOFWEEK(sent_at) as dow'), DB::raw('COUNT(*) as total'))
            ->groupBy('dow')
            ->orderBy('dow')
            ->get()
            ->keyBy('dow');

        $days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $labels = [];
        $data = [];

        for ($i = 1; $i <= 7; $i++) {
            $labels[] = $days[$i - 1];
            $data[] = $results->get($i)?->total ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    #[Computed]
    public function messagesByType(): array
    {
        $results = $this->getBaseQuery()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        $typeLabels = [
            'text' => 'Texto',
            'image' => 'Imagen',
            'audio' => 'Audio',
            'video' => 'Video',
            'document' => 'Documento',
            'sticker' => 'Sticker',
            'location' => 'Ubicación',
            'contact' => 'Contacto',
            'other' => 'Otro',
        ];

        return $results->map(fn($r) => [
            'type' => $typeLabels[$r->type] ?? $r->type,
            'total' => $r->total,
        ])->toArray();
    }

    #[Computed]
    public function topContacts(): array
    {
        $results = Message::query()
            ->join('contacts', 'messages.contact_id', '=', 'contacts.id')
            ->whereBetween('messages.sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
            ->when($this->userId, fn($q) => $q->where('messages.user_id', $this->userId))
            ->when($this->channelId, fn($q) => $q->where('messages.channel_id', $this->channelId))
            ->select(
                'contacts.id',
                'contacts.phone_number',
                'contacts.name',
                'contacts.push_name',
                DB::raw('COUNT(*) as total_messages'),
                DB::raw('SUM(CASE WHEN messages.direction = "incoming" THEN 1 ELSE 0 END) as incoming'),
                DB::raw('SUM(CASE WHEN messages.direction = "outgoing" THEN 1 ELSE 0 END) as outgoing')
            )
            ->groupBy('contacts.id', 'contacts.phone_number', 'contacts.name', 'contacts.push_name')
            ->orderByDesc('total_messages')
            ->limit(10)
            ->get();

        return $results->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name ?: $r->push_name ?: $r->phone_number,
            'phone' => $r->phone_number,
            'total' => $r->total_messages,
            'incoming' => $r->incoming,
            'outgoing' => $r->outgoing,
        ])->toArray();
    }

    /**
     * Tabla detallada de contactos con toda la información solicitada
     */
    public function getContactsTableProperty()
    {
        return Contact::query()
            ->select([
                'contacts.id',
                'contacts.name',
                'contacts.push_name',
                'contacts.phone_number',
                'contacts.assigned_user_id',
                'contacts.channel_id',
                'contacts.created_at',
            ])
            ->with(['assignedUser:id,name', 'channel:id,name', 'labelRelations:id,name,color'])
            ->withCount([
                'messages as total_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
                },
                'messages as sent_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'outgoing');
                },
                'messages as received_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'incoming');
                },
            ])
            ->withMax('messages as last_message_at', 'sent_at')
            ->when($this->userId, fn($q) => $q->where('contacts.assigned_user_id', $this->userId))
            ->when($this->channelId, fn($q) => $q->where('contacts.channel_id', $this->channelId))
            ->whereHas('messages', function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            })
            ->orderByDesc('last_message_at')
            ->paginate($this->perPage);
    }

    /**
     * Datos para exportar a PDF
     */
    public function getExportData(): array
    {
        $contacts = Contact::query()
            ->select([
                'contacts.id',
                'contacts.name',
                'contacts.push_name',
                'contacts.phone_number',
                'contacts.assigned_user_id',
                'contacts.channel_id',
                'contacts.created_at',
            ])
            ->with(['assignedUser:id,name', 'channel:id,name', 'labelRelations:id,name,color'])
            ->withCount([
                'messages as total_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
                },
                'messages as sent_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'outgoing');
                },
                'messages as received_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'incoming');
                },
            ])
            ->withMax('messages as last_message_at', 'sent_at')
            ->when($this->userId, fn($q) => $q->where('contacts.assigned_user_id', $this->userId))
            ->when($this->channelId, fn($q) => $q->where('contacts.channel_id', $this->channelId))
            ->whereHas('messages', function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            })
            ->orderByDesc('last_message_at')
            ->get();

        $selectedUser = $this->userId ? User::find($this->userId) : null;

        return [
            'contacts' => $contacts,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'selectedUser' => $selectedUser,
            'totalMessages' => $this->totalMessages,
            'totalConversations' => $this->totalConversations,
            'totalIncoming' => $this->totalIncoming,
            'totalOutgoing' => $this->totalOutgoing,
            'messagesByUser' => $this->messagesByUser,
        ];
    }

    private function getBaseQuery()
    {
        return Message::query()
            ->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
            ->when($this->userId, fn($q) => $q->where('user_id', $this->userId))
            ->when($this->channelId, fn($q) => $q->where('channel_id', $this->channelId))
            ->when($this->messageDirection !== 'all', fn($q) => $q->where('direction', $this->messageDirection));
    }

    private function formatDateLabel(string $dateGroup): string
    {
        return match($this->groupBy) {
            'week' => 'Sem ' . substr($dateGroup, -2),
            'month' => Carbon::createFromFormat('Y-m', $dateGroup)->translatedFormat('M Y'),
            default => Carbon::parse($dateGroup)->format('d/m'),
        };
    }

    private function getUserColor(int $userId): string
    {
        $colors = [
            '#f97316', '#ec4899', '#8b5cf6', '#06b6d4', '#10b981',
            '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#84cc16',
        ];
        return $colors[$userId % count($colors)];
    }

    public function resetFilters(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->userId = null;
        $this->channelId = null;
        $this->groupBy = 'day';
        $this->messageDirection = 'all';
        
        $this->dispatchChartsUpdate();
    }

    public function setQuickDate(string $period): void
    {
        $this->dateTo = now()->format('Y-m-d');
        
        $this->dateFrom = match($period) {
            'today' => now()->format('Y-m-d'),
            'yesterday' => now()->subDay()->format('Y-m-d'),
            'week' => now()->startOfWeek()->format('Y-m-d'),
            'month' => now()->startOfMonth()->format('Y-m-d'),
            'quarter' => now()->subMonths(3)->format('Y-m-d'),
            'year' => now()->startOfYear()->format('Y-m-d'),
            default => now()->subDays(30)->format('Y-m-d'),
        };

        if ($period === 'yesterday') {
            $this->dateTo = now()->subDay()->format('Y-m-d');
        }
        
        $this->dispatchChartsUpdate();
    }

    public function updated($property): void
    {
        // Dispatch chart update when any filter changes
        if (in_array($property, ['dateFrom', 'dateTo', 'userId', 'channelId', 'groupBy', 'messageDirection'])) {
            $this->resetPage();
            $this->dispatchChartsUpdate();
        }
    }

    private function dispatchChartsUpdate(): void
    {
        // Clear computed caches
        unset($this->messagesByDate);
        unset($this->messagesByHour);
        unset($this->messagesByDayOfWeek);
        unset($this->messagesByType);
        unset($this->messagesByChannel);
        unset($this->totalMessages);
        unset($this->totalConversations);
        unset($this->totalIncoming);
        unset($this->totalOutgoing);
        unset($this->avgMessagesPerDay);
        unset($this->messagesByUser);
        unset($this->topContacts);
        unset($this->contactsTable);
        
        $this->dispatch('charts-updated', [
            'messagesByDate' => $this->messagesByDate,
            'messagesByHour' => $this->messagesByHour,
            'messagesByDayOfWeek' => $this->messagesByDayOfWeek,
            'messagesByType' => $this->messagesByType,
            'messagesByChannel' => $this->messagesByChannel,
        ]);
    }

    public function render()
    {
        return view('livewire.reports.chats-attended');
    }
}
