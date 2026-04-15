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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                    ->whereNotNull('messages.user_id')
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

    #[Computed]
    public function avgConversationsPerDay(): float
    {
        $days = Carbon::parse($this->dateFrom)->diffInDays(Carbon::parse($this->dateTo)) + 1;
        return $days > 0 ? round($this->totalEffectiveConversations / $days, 1) : 0;
    }

    #[Computed]
    public function topAgent(): ?array
    {
        $users = $this->effectiveConversationsByUser;
        return count($users) > 0 ? $users[0] : null;
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
        unset($this->avgConversationsPerDay);
        unset($this->topAgent);
        
        $this->dispatch('charts-updated', [
            'conversationsByDate' => $this->effectiveConversationsByDate,
        ]);
    }

    public function exportExcel(): StreamedResponse
    {
        $dates = $this->getBaseDateRange();

        // Get contacts that have at least one outgoing message from an agent in the range
        $contacts = Contact::query()
            ->with(['assignedUser', 'labelRelations'])
            ->whereExists(function ($query) use ($dates) {
                $query->select(DB::raw(1))
                    ->from('messages')
                    ->whereColumn('messages.contact_id', 'contacts.id')
                    ->where('messages.direction', 'outgoing')
                    ->whereNotNull('messages.user_id')
                    ->whereBetween('messages.sent_at', $dates);

                if ($this->channelId) {
                    $query->where('messages.channel_id', $this->channelId);
                }
            })
            ->where('is_group', false)
            ->get();

        // Pre-load message counts and agent info per contact
        $contactIds = $contacts->pluck('id');

        // Outgoing counts in range
        $outgoingCounts = Message::whereIn('contact_id', $contactIds)
            ->where('direction', 'outgoing')
            ->whereBetween('sent_at', $dates)
            ->when($this->channelId, fn($q) => $q->where('channel_id', $this->channelId))
            ->groupBy('contact_id')
            ->selectRaw('contact_id, COUNT(*) as total')
            ->pluck('total', 'contact_id');

        // Incoming counts in range
        $incomingCounts = Message::whereIn('contact_id', $contactIds)
            ->where('direction', 'incoming')
            ->whereBetween('sent_at', $dates)
            ->when($this->channelId, fn($q) => $q->where('channel_id', $this->channelId))
            ->groupBy('contact_id')
            ->selectRaw('contact_id, COUNT(*) as total')
            ->pluck('total', 'contact_id');

        // Effective conversations: contacts that have BOTH outgoing (from agent) AND incoming
        $effectiveContactIds = Message::whereIn('contact_id', $contactIds)
            ->where('direction', 'incoming')
            ->whereBetween('sent_at', $dates)
            ->when($this->channelId, fn($q) => $q->where('channel_id', $this->channelId))
            ->pluck('contact_id')
            ->unique();

        // Primary agent per contact (user who sent most outgoing messages in range)
        $primaryAgents = Message::whereIn('contact_id', $contactIds)
            ->where('direction', 'outgoing')
            ->whereNotNull('user_id')
            ->whereBetween('sent_at', $dates)
            ->when($this->channelId, fn($q) => $q->where('channel_id', $this->channelId))
            ->groupBy('contact_id', 'user_id')
            ->selectRaw('contact_id, user_id, COUNT(*) as total')
            ->orderByDesc('total')
            ->get()
            ->unique('contact_id')
            ->keyBy('contact_id');

        $userIds = $primaryAgents->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)->pluck('name', 'id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            'ID', 'Nombre', 'Número de WhatsApp', 'Etiquetas',
            'Fecha de ultimo contacto', 'Hora de ultimo contacto',
            'Fecha de creación', 'Agente Principal', 'ID del Agente Principal',
            'Mensajes Enviados', 'Mensajes Recibidos', 'Conversaciones',
        ];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        $index = 1;
        foreach ($contacts as $contact) {
            // Phone number without +57 prefix
            $phone = $contact->phone_number;
            if (str_starts_with($phone, '57') && strlen($phone) === 12) {
                $phone = substr($phone, 2);
            }

            // Name
            $name = $contact->name ?: ($contact->push_name ?: '-');

            // Labels from relation
            $labels = $contact->labelRelations->pluck('name')->implode(', ');

            // Last contact date/time
            $lastMessageAt = $contact->last_message_at;
            $lastDate = $lastMessageAt ? $lastMessageAt->format('d/m/Y') : '-';
            $lastTime = $lastMessageAt ? $lastMessageAt->format('h:i:s A') : '-';

            // Created at
            $createdAt = $contact->created_at ? $contact->created_at->format('d/m/Y') : '-';

            // Primary agent
            $agentInfo = $primaryAgents->get($contact->id);
            $agentName = $agentInfo ? ($users->get($agentInfo->user_id) ?? '-') : '-';

            // Counts
            $sent = $outgoingCounts->get($contact->id, 0);
            $received = $incomingCounts->get($contact->id, 0);
            $conversations = $effectiveContactIds->contains($contact->id) ? 1 : 0;

            $sheet->setCellValue([1, $row], $index);
            $sheet->setCellValue([2, $row], $name);
            $sheet->setCellValueExplicit([3, $row], $phone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([4, $row], $labels);
            $sheet->setCellValue([5, $row], $lastDate);
            $sheet->setCellValue([6, $row], $lastTime);
            $sheet->setCellValue([7, $row], $createdAt);
            $sheet->setCellValue([8, $row], $agentName);
            $sheet->setCellValue([9, $row], $agentName);
            $sheet->setCellValue([10, $row], $sent);
            $sheet->setCellValue([11, $row], $received);
            $sheet->setCellValue([12, $row], $conversations);

            $row++;
            $index++;
        }

        $fileName = 'conversaciones_efectivas_' . $this->dateFrom . '_' . $this->dateTo . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.effective-conversations');
    }
}
