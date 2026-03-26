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

#[Layout('layouts.app')]
#[Title('Conversaciones Efectivas')]
class EffectiveConversations extends Component
{
    #[Url]
    public string $dateFrom = '';
    
    #[Url]
    public string $dateTo = '';
    
    #[Url]
    public ?int $channelId = null;

    #[Url]
    public string $groupBy = 'day';

    public function mount(): void
    {
        if (empty($this->dateFrom)) {
            $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        }
        if (empty($this->dateTo)) {
            $this->dateTo = now()->format('Y-m-d');
        }
    }

    #[Computed]
    public function channels(): Collection
    {
        return Channel::where('is_active', true)->orderBy('name')->get();
    }

    private function getBaseDateRange(): array
    {
        return [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'];
    }

    #[Computed]
    public function totalEffectiveConversations(): int
    {
        $dates = $this->getBaseDateRange();

        // Contactos únicos que tienen al menos un mensaje entrante y uno saliente en el rango
        $query = DB::table('contacts')
            ->whereExists(function ($query) use ($dates) {
                $query->select(DB::raw(1))
                    ->from('messages')
                    ->whereColumn('messages.contact_id', 'contacts.id')
                    ->where('messages.direction', 'outgoing')
                    ->whereBetween('messages.sent_at', $dates);
                    
                if ($this->channelId) {
                    $query->where('messages.channel_id', $this->channelId);
                }
            })
            ->whereExists(function ($query) use ($dates) {
                $query->select(DB::raw(1))
                    ->from('messages')
                    ->whereColumn('messages.contact_id', 'contacts.id')
                    ->where('messages.direction', 'incoming')
                    ->whereBetween('messages.sent_at', $dates);
                    
                if ($this->channelId) {
                    $query->where('messages.channel_id', $this->channelId);
                }
            });

        return $query->count();
    }

    #[Computed]
    public function effectiveConversationsByUser(): array
    {
        $dates = $this->getBaseDateRange();

        $query = DB::table('users')
            ->select('users.id', 'users.name', DB::raw('COUNT(DISTINCT msg_out.contact_id) as total_effective'))
            ->join('messages as msg_out', function ($join) use ($dates) {
                $join->on('users.id', '=', 'msg_out.user_id')
                    ->where('msg_out.direction', '=', 'outgoing')
                    ->whereBetween('msg_out.sent_at', $dates);
            })
            ->whereExists(function ($query) use ($dates) {
                $query->select(DB::raw(1))
                    ->from('messages as msg_in')
                    ->whereColumn('msg_in.contact_id', 'msg_out.contact_id')
                    ->where('msg_in.direction', 'incoming')
                    ->whereBetween('msg_in.sent_at', $dates);
            });

        if ($this->channelId) {
            $query->where('msg_out.channel_id', $this->channelId);
            // También deberíamos asegurar que el incoming es del mismo canal, aunque el contact_id suele pertenecer a un canal específico
        }

        $results = $query->groupBy('users.id', 'users.name')
            ->orderByDesc('total_effective')
            ->get();

        return $results->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'total' => $r->total_effective,
                'color' => $this->getUserColor($r->id),
            ];
        })->toArray();
    }

    #[Computed]
    public function effectiveConversationsByDate(): array
    {
        $dates = $this->getBaseDateRange();
        $format = match($this->groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        // Queremos saber la fecha en la que ocurrió la conversación. 
        // Como la conversación abarca el rango, agruparemos usando la fecha del primer mensaje saliente en el rango como fecha de referencia.
        $query = DB::table('messages as msg_out')
            ->select(DB::raw("DATE_FORMAT(msg_out.sent_at, '{$format}') as date_group"), DB::raw('COUNT(DISTINCT msg_out.contact_id) as total'))
            ->where('msg_out.direction', 'outgoing')
            ->whereBetween('msg_out.sent_at', $dates)
            ->whereExists(function ($q) use ($dates) {
                $q->select(DB::raw(1))
                    ->from('messages as msg_in')
                    ->whereColumn('msg_in.contact_id', 'msg_out.contact_id')
                    ->where('msg_in.direction', 'incoming')
                    ->whereBetween('msg_in.sent_at', $dates);
            });

        if ($this->channelId) {
            $query->where('msg_out.channel_id', $this->channelId);
        }

        $results = $query->groupBy('date_group')
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
            '#06b6d4', '#8b5cf6', '#ec4899', '#f97316', '#10b981',
            '#6366f1', '#ef4444', '#f59e0b', '#14b8a6', '#84cc16',
        ];
        return $colors[$userId % count($colors)];
    }

    public function resetFilters(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->channelId = null;
        $this->groupBy = 'day';
        
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
        if (in_array($property, ['dateFrom', 'dateTo', 'channelId', 'groupBy'])) {
            $this->dispatchChartsUpdate();
        }
    }

    private function dispatchChartsUpdate(): void
    {
        unset($this->totalEffectiveConversations);
        unset($this->effectiveConversationsByUser);
        unset($this->effectiveConversationsByDate);
        
        $this->dispatch('charts-updated', [
            'conversationsByDate' => $this->effectiveConversationsByDate,
        ]);
    }

    public function render()
    {
        return view('livewire.reports.effective-conversations');
    }
}
