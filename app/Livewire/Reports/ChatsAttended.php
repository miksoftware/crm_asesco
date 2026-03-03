<?php

namespace App\Livewire\Reports;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
                'messages as sent_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'outgoing');
                },
                'messages as received_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'incoming');
                },
            ])
            ->withMin(['messages as first_message_at' => function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            }], 'sent_at')
            ->when($this->userId, fn($q) => $q->where('contacts.assigned_user_id', $this->userId))
            ->when($this->channelId, fn($q) => $q->where('contacts.channel_id', $this->channelId))
            ->whereHas('messages', function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            })
            ->orderByDesc('first_message_at')
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
                'messages as sent_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'outgoing');
                },
                'messages as received_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'incoming');
                },
            ])
            ->withMin(['messages as first_message_at' => function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            }], 'sent_at')
            ->when($this->userId, fn($q) => $q->where('contacts.assigned_user_id', $this->userId))
            ->when($this->channelId, fn($q) => $q->where('contacts.channel_id', $this->channelId))
            ->whereHas('messages', function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            })
            ->orderByDesc('first_message_at')
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

    /**
     * Exportar reporte a Excel (.xlsx)
     */
    public function exportToExcel()
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
                'messages as sent_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'outgoing');
                },
                'messages as received_messages' => function ($q) {
                    $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
                      ->where('direction', 'incoming');
                },
            ])
            ->withMin(['messages as first_message_at' => function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            }], 'sent_at')
            ->when($this->userId, fn($q) => $q->where('contacts.assigned_user_id', $this->userId))
            ->when($this->channelId, fn($q) => $q->where('contacts.channel_id', $this->channelId))
            ->whereHas('messages', function ($q) {
                $q->whereBetween('sent_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59']);
            })
            ->orderByDesc('first_message_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte Chats');

        // -- Encabezado del reporte --
        $sheet->setCellValue('A1', 'ASESCO BPO - Reporte de Chats Atendidos');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('F97316');

        $selectedUser = $this->userId ? User::find($this->userId) : null;
        $sheet->setCellValue('A2', 'Período: ' . $this->dateFrom . ' al ' . $this->dateTo);
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A3', 'Agente: ' . ($selectedUser ? $selectedUser->name : 'Todos los agentes'));
        $sheet->mergeCells('A3:I3');
        $sheet->setCellValue('A4', 'Generado: ' . now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A4:I4');
        $sheet->getStyle('A2:A4')->getFont()->setSize(10)->setItalic(true);

        // -- Resumen KPIs --
        $kpiRow = 6;
        $sheet->setCellValue('A' . $kpiRow, 'Total Mensajes');
        $sheet->setCellValue('B' . $kpiRow, $this->totalMessages);
        $sheet->setCellValue('C' . $kpiRow, 'Conversaciones');
        $sheet->setCellValue('D' . $kpiRow, $this->totalConversations);
        $sheet->setCellValue('E' . $kpiRow, 'Recibidos');
        $sheet->setCellValue('F' . $kpiRow, $this->totalIncoming);
        $sheet->setCellValue('G' . $kpiRow, 'Enviados');
        $sheet->setCellValue('H' . $kpiRow, $this->totalOutgoing);
        $sheet->getStyle('A' . $kpiRow . ':H' . $kpiRow)->getFont()->setBold(true);
        $sheet->getStyle('B' . $kpiRow)->getFont()->setSize(12);
        $sheet->getStyle('D' . $kpiRow)->getFont()->setSize(12);
        $sheet->getStyle('F' . $kpiRow)->getFont()->setSize(12);
        $sheet->getStyle('H' . $kpiRow)->getFont()->setSize(12);

        // -- Headers de la tabla --
        $headerRow = 8;
        $headers = ['Nombre', 'WhatsApp', 'Canal', 'Etiquetas', 'Fecha Creación', 'Agente Principal', 'Enviados', 'Recibidos', 'Conversaciones'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . $headerRow;
            $sheet->setCellValue($cell, $header);
        }

        // Estilo de headers
        $headerRange = 'A' . $headerRow . ':I' . $headerRow;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F97316']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        // -- Datos --
        $row = $headerRow + 1;
        foreach ($contacts as $contact) {
            $name = $contact->name ?: $contact->push_name ?: '-';
            $labels = $contact->labelRelations->pluck('name')->implode(', ') ?: '-';
            $channelName = $contact->channel?->name ?: '-';
            $firstMessage = $contact->first_message_at
                ? Carbon::parse($contact->first_message_at)->format('d/m/Y H:i')
                : '-';
            $agent = $contact->assignedUser?->name ?: 'Sin asignar';
            $conversations = ($contact->sent_messages > 0 && $contact->received_messages > 0) ? 1 : 0;

            $sheet->setCellValue('A' . $row, $name);
            $sheet->setCellValue('B' . $row, "'" . $contact->phone_number); // Prefijo ' para evitar formato numérico
            $sheet->setCellValue('C' . $row, $channelName);
            $sheet->setCellValue('D' . $row, $labels);
            $sheet->setCellValue('E' . $row, $firstMessage);
            $sheet->setCellValue('F' . $row, $agent);
            $sheet->setCellValue('G' . $row, $contact->sent_messages);
            $sheet->setCellValue('H' . $row, $contact->received_messages);
            $sheet->setCellValue('I' . $row, $conversations);

            // Bordes para la fila
            $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            // Alternar color de fondo
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
                ]);
            }

            // Centrar columnas numéricas
            $sheet->getStyle('G' . $row . ':I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        // -- Fila de totales --
        $totalRow = $row;
        $sheet->setCellValue('A' . $totalRow, 'TOTALES');
        $sheet->mergeCells('A' . $totalRow . ':F' . $totalRow);
        $sheet->setCellValue('G' . $totalRow, $contacts->sum('sent_messages'));
        $sheet->setCellValue('H' . $totalRow, $contacts->sum('received_messages'));
        $sheet->setCellValue('I' . $totalRow, $contacts->filter(fn($c) => $c->sent_messages > 0 && $c->received_messages > 0)->count());
        $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // -- Auto-dimensionar columnas --
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // -- Generar archivo y devolver descarga --
        $filename = 'reporte_chats_' . $this->dateFrom . '_a_' . $this->dateTo . '.xlsx';
        $tempPath = storage_path('app/' . $filename);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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
