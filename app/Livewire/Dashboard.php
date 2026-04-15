<?php

namespace App\Livewire;

use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Message;
use App\Models\PaymentPromise;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public string $period = 'today';

    public bool $isAdmin = false;

    public function mount(): void
    {
        $this->isAdmin = auth()->user()->hasRole('admin');
    }

    // ── Helpers ──────────────────────────────────────────────

    private function dateRange(): array
    {
        return match ($this->period) {
            'today'     => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week'      => [now()->startOfWeek(), now()->endOfDay()],
            'month'     => [now()->startOfMonth(), now()->endOfDay()],
            default     => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function previousDateRange(): array
    {
        [$start, $end] = $this->dateRange();
        $diff = $start->diffInSeconds($end);

        return [
            $start->copy()->subSeconds($diff + 1),
            $start->copy()->subSecond(),
        ];
    }

    private function userScope($query, string $column = 'user_id')
    {
        if (! $this->isAdmin) {
            $query->where($column, auth()->id());
        }

        return $query;
    }

    private function channelIds(): array
    {
        if ($this->isAdmin) {
            return Channel::where('is_active', true)->pluck('id')->toArray();
        }

        return auth()->user()->channels()->pluck('channels.id')->toArray();
    }

    private function percentChange(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    // ── KPI Cards ───────────────────────────────────────────

    #[Computed]
    public function messagesSent(): array
    {
        [$from, $to] = $this->dateRange();
        [$prevFrom, $prevTo] = $this->previousDateRange();

        $current = Message::where('direction', 'outgoing')
            ->whereBetween('sent_at', [$from, $to])
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->count();

        $previous = Message::where('direction', 'outgoing')
            ->whereBetween('sent_at', [$prevFrom, $prevTo])
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->count();

        return [
            'value' => $current,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    #[Computed]
    public function messagesReceived(): array
    {
        [$from, $to] = $this->dateRange();
        [$prevFrom, $prevTo] = $this->previousDateRange();

        $channelIds = $this->channelIds();

        $current = Message::where('direction', 'incoming')
            ->whereIn('channel_id', $channelIds)
            ->whereBetween('sent_at', [$from, $to])
            ->count();

        $previous = Message::where('direction', 'incoming')
            ->whereIn('channel_id', $channelIds)
            ->whereBetween('sent_at', [$prevFrom, $prevTo])
            ->count();

        return [
            'value' => $current,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    #[Computed]
    public function paymentPromisesToday(): array
    {
        [$from, $to] = $this->dateRange();
        [$prevFrom, $prevTo] = $this->previousDateRange();

        $currentQuery = PaymentPromise::whereBetween('created_at', [$from, $to]);
        $previousQuery = PaymentPromise::whereBetween('created_at', [$prevFrom, $prevTo]);

        if (! $this->isAdmin) {
            $currentQuery->where('user_id', auth()->id());
            $previousQuery->where('user_id', auth()->id());
        }

        $currentAmount = (float) $currentQuery->sum('promised_amount');
        $previousAmount = (float) $previousQuery->sum('promised_amount');
        $currentCount = PaymentPromise::whereBetween('created_at', [$from, $to])
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->count();

        return [
            'amount' => $currentAmount,
            'count' => $currentCount,
            'change' => $this->percentChange($currentAmount, $previousAmount),
        ];
    }

    #[Computed]
    public function effectiveConversations(): array
    {
        [$from, $to] = $this->dateRange();
        [$prevFrom, $prevTo] = $this->previousDateRange();

        $current = $this->countEffective($from, $to);
        $previous = $this->countEffective($prevFrom, $prevTo);

        return [
            'value' => $current,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    private function countEffective($from, $to): int
    {
        $query = DB::table('contacts')
            ->whereExists(function ($q) use ($from, $to) {
                $q->select(DB::raw(1))
                    ->from('messages')
                    ->whereColumn('messages.contact_id', 'contacts.id')
                    ->where('messages.direction', 'outgoing')
                    ->whereNotNull('messages.user_id')
                    ->whereBetween('messages.sent_at', [$from, $to]);

                if (! $this->isAdmin) {
                    $q->where('messages.user_id', auth()->id());
                }
            })
            ->whereExists(function ($q) use ($from, $to) {
                $q->select(DB::raw(1))
                    ->from('messages')
                    ->whereColumn('messages.contact_id', 'contacts.id')
                    ->where('messages.direction', 'incoming')
                    ->whereBetween('messages.sent_at', [$from, $to]);
            });

        return $query->count();
    }

    // ── Follow-ups pendientes ───────────────────────────────

    #[Computed]
    public function pendingFollowUps(): int
    {
        return FollowUp::where('status', 'pending')
            ->where('scheduled_date', '<=', now()->endOfDay())
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->count();
    }

    #[Computed]
    public function upcomingFollowUps(): array
    {
        return FollowUp::with(['contact', 'user'])
            ->where('status', 'pending')
            ->where('scheduled_date', '>=', now()->startOfDay())
            ->where('scheduled_date', '<=', now()->addDays(3)->endOfDay())
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'contact_name' => $f->contact?->display_name ?? 'Sin contacto',
                'note' => $f->note,
                'scheduled_date' => $f->scheduled_date,
                'user_name' => $f->user?->name ?? '-',
                'is_overdue' => $f->scheduled_date->isPast(),
                'is_today' => $f->scheduled_date->isToday(),
            ])
            ->toArray();
    }

    // ── Promesas de pago pendientes para hoy ────────────────

    #[Computed]
    public function promisesDueToday(): array
    {
        $promises = PaymentPromise::with(['contact', 'user'])
            ->whereDate('promised_date', today())
            ->where('status', 'pending')
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->orderByDesc('promised_amount')
            ->limit(5)
            ->get();

        return $promises->map(fn ($p) => [
            'id' => $p->id,
            'contact_name' => $p->contact?->display_name ?? 'Sin contacto',
            'amount' => $p->promised_amount,
            'user_name' => $p->user?->name ?? '-',
            'message_sent' => $p->message_sent,
        ])->toArray();
    }

    #[Computed]
    public function promisesDueTodayTotal(): float
    {
        return (float) PaymentPromise::whereDate('promised_date', today())
            ->where('status', 'pending')
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->sum('promised_amount');
    }

    // ── Gráfico: Mensajes últimos 7 días ────────────────────

    #[Computed]
    public function messagesChart(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $outgoing = Message::where('direction', 'outgoing')
            ->whereBetween('sent_at', [$days->first() . ' 00:00:00', $days->last() . ' 23:59:59'])
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->selectRaw("DATE(sent_at) as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $incoming = Message::where('direction', 'incoming')
            ->whereIn('channel_id', $this->channelIds())
            ->whereBetween('sent_at', [$days->first() . ' 00:00:00', $days->last() . ' 23:59:59'])
            ->selectRaw("DATE(sent_at) as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->translatedFormat('D d'))->toArray(),
            'outgoing' => $days->map(fn ($d) => $outgoing[$d] ?? 0)->toArray(),
            'incoming' => $days->map(fn ($d) => $incoming[$d] ?? 0)->toArray(),
        ];
    }

    // ── Ranking de agentes (solo admin) ─────────────────────

    #[Computed]
    public function agentRanking(): array
    {
        if (! $this->isAdmin) {
            return [];
        }

        [$from, $to] = $this->dateRange();

        $agents = DB::table('messages')
            ->join('users', 'users.id', '=', 'messages.user_id')
            ->where('messages.direction', 'outgoing')
            ->whereNotNull('messages.user_id')
            ->whereBetween('messages.sent_at', [$from, $to])
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as messages_sent'),
                DB::raw('COUNT(DISTINCT messages.contact_id) as contacts_attended')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('messages_sent')
            ->limit(10)
            ->get();

        // Enriquecer con promesas de pago
        $userIds = $agents->pluck('id');
        $promises = PaymentPromise::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, COUNT(*) as promise_count, SUM(promised_amount) as promise_total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $agents->map(function ($agent, $index) use ($promises) {
            $p = $promises->get($agent->id);

            return [
                'position' => $index + 1,
                'name' => $agent->name,
                'messages_sent' => $agent->messages_sent,
                'contacts_attended' => $agent->contacts_attended,
                'promise_count' => $p?->promise_count ?? 0,
                'promise_total' => (float) ($p?->promise_total ?? 0),
            ];
        })->toArray();
    }

    // ── Estado de canales (solo admin) ──────────────────────

    #[Computed]
    public function channelStatuses(): array
    {
        if (! $this->isAdmin) {
            return [];
        }

        return Channel::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($ch) => [
                'name' => $ch->name,
                'status' => $ch->status,
                'status_label' => $ch->status_label,
                'status_color' => $ch->status_color,
                'phone' => $ch->phone_number,
            ])
            ->toArray();
    }

    // ── Resumen de promesas por estado (admin) ──────────────

    #[Computed]
    public function promiseSummary(): array
    {
        [$from, $to] = $this->dateRange();

        $data = PaymentPromise::whereBetween('created_at', [$from, $to])
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->selectRaw("status, COUNT(*) as total, SUM(promised_amount) as amount")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'pending' => [
                'count' => $data->get('pending')?->total ?? 0,
                'amount' => (float) ($data->get('pending')?->amount ?? 0),
            ],
            'fulfilled' => [
                'count' => $data->get('fulfilled')?->total ?? 0,
                'amount' => (float) ($data->get('fulfilled')?->amount ?? 0),
            ],
            'broken' => [
                'count' => $data->get('broken')?->total ?? 0,
                'amount' => (float) ($data->get('broken')?->amount ?? 0),
            ],
        ];
    }

    // ── Mi rendimiento personal (solo agentes) ──────────────

    #[Computed]
    public function myPerformance(): array
    {
        if ($this->isAdmin) {
            return [];
        }

        $userId = auth()->id();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        // Mensajes hoy
        $messagesToday = Message::where('user_id', $userId)
            ->where('direction', 'outgoing')
            ->whereBetween('sent_at', [$todayStart, $todayEnd])
            ->count();

        // Mensajes esta semana
        $messagesWeek = Message::where('user_id', $userId)
            ->where('direction', 'outgoing')
            ->whereBetween('sent_at', [$weekStart, $todayEnd])
            ->count();

        // Mensajes este mes
        $messagesMonth = Message::where('user_id', $userId)
            ->where('direction', 'outgoing')
            ->whereBetween('sent_at', [$monthStart, $todayEnd])
            ->count();

        // Contactos atendidos hoy
        $contactsToday = Message::where('user_id', $userId)
            ->where('direction', 'outgoing')
            ->whereBetween('sent_at', [$todayStart, $todayEnd])
            ->distinct('contact_id')
            ->count('contact_id');

        // Promesas este mes
        $promisesMonth = PaymentPromise::where('user_id', $userId)
            ->whereBetween('created_at', [$monthStart, $todayEnd])
            ->selectRaw('COUNT(*) as total, SUM(promised_amount) as amount')
            ->first();

        // Seguimientos completados este mes
        $followUpsCompleted = FollowUp::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$monthStart, $todayEnd])
            ->count();

        return [
            'messages_today' => $messagesToday,
            'messages_week' => $messagesWeek,
            'messages_month' => $messagesMonth,
            'contacts_today' => $contactsToday,
            'promises_month_count' => $promisesMonth->total ?? 0,
            'promises_month_amount' => (float) ($promisesMonth->amount ?? 0),
            'followups_completed' => $followUpsCompleted,
        ];
    }

    // ── Actividad reciente ──────────────────────────────────

    #[Computed]
    public function recentActivity(): array
    {
        $activities = collect();

        // Últimas promesas de pago
        $promises = PaymentPromise::with(['contact', 'user'])
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($p) => [
                'type' => 'promise',
                'icon' => 'dollar',
                'color' => 'green',
                'text' => ($this->isAdmin ? $p->user?->name . ': ' : '') . 'Promesa de pago - ' . ($p->contact?->display_name ?? 'Contacto'),
                'detail' => '$' . number_format($p->promised_amount, 0, ',', '.'),
                'time' => $p->created_at,
            ]);

        $activities = $activities->merge($promises);

        // Últimos seguimientos completados
        $followUps = FollowUp::with(['contact', 'user'])
            ->where('status', 'completed')
            ->when(! $this->isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->latest('completed_at')
            ->limit(3)
            ->get()
            ->map(fn ($f) => [
                'type' => 'followup',
                'icon' => 'check',
                'color' => 'blue',
                'text' => ($this->isAdmin ? $f->user?->name . ': ' : '') . 'Seguimiento completado - ' . ($f->contact?->display_name ?? 'Contacto'),
                'detail' => null,
                'time' => $f->completed_at,
            ]);

        $activities = $activities->merge($followUps);

        return $activities->sortByDesc('time')->take(5)->values()->toArray();
    }

    // ── Period change ───────────────────────────────────────

    public function setPeriod(string $period): void
    {
        $this->period = $period;

        // Clear computed caches
        unset(
            $this->messagesSent,
            $this->messagesReceived,
            $this->paymentPromisesToday,
            $this->effectiveConversations,
            $this->pendingFollowUps,
            $this->agentRanking,
            $this->promiseSummary,
            $this->messagesChart,
        );

        $this->dispatch('period-changed');
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
