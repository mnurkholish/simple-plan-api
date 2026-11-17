<?php

namespace App\Services;

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class HelpdeskReportService
{
    /**
     * Shared scope for report aggregates and a future export.
     *
     * @param  array{periode_awal: string, periode_akhir: string, jenis_layanan?: string|null}  $filters
     */
    public function query(array $filters, User $actor): Builder
    {
        abort_unless($actor->hasAnyRole(['super-admin', 'management', 'koordinator-sarpras']), 403);

        $query = Ticket::query()
            ->where('tickets.created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $filters['periode_awal']))
            ->where('tickets.created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $filters['periode_akhir'])->addDay());

        if ($actor->hasRole('koordinator-sarpras')) {
            $query->where('tickets.service', TicketService::Sarpras->value);
        }

        if (($filters['jenis_layanan'] ?? 'semua') !== 'semua') {
            $query->where('tickets.service', $filters['jenis_layanan']);
        }

        return $query;
    }

    /** @param array{periode_awal: string, periode_akhir: string, jenis_layanan?: string|null} $filters */
    public function report(array $filters, User $actor): array
    {
        $status = [];
        foreach (TicketStatus::cases() as $case) {
            $status[$case->value] = 0;
        }

        $months = [];
        $month = CarbonImmutable::createFromFormat('!Y-m-d', $filters['periode_awal'])->startOfMonth();
        $lastMonth = CarbonImmutable::createFromFormat('!Y-m-d', $filters['periode_akhir'])->startOfMonth();
        while ($month <= $lastMonth) {
            $months[$month->format('Y-m')] = 0;
            $month = $month->addMonth();
        }

        $categories = [TicketService::Sarpras->value => [], TicketService::Tik->value => []];
        $sla = ['tercapai' => 0, 'terlambat' => 0];
        $completed = 0;
        $rejected = 0;
        $durationSeconds = 0;
        $durationCount = 0;

        $this->query($filters, $actor)
            ->select(['tickets.id', 'tickets.service', 'tickets.status', 'tickets.created_at', 'tickets.completed_at', 'tickets.sla_deadline'])
            ->with(['tikDetail.itTag:id,name', 'sarprasDetail.sarprasCategory:id,name'])
            ->chunkById(500, function (Collection $tickets) use (&$months, &$status, &$categories, &$sla, &$completed, &$rejected, &$durationSeconds, &$durationCount): void {
                foreach ($tickets as $ticket) {
                    $status[$ticket->status->value]++;
                    $months[$ticket->created_at->format('Y-m')]++;

                    $service = $ticket->service;
                    if ($service === TicketService::Tik) {
                        $category = $ticket->tikDetail?->itTag?->name ?? 'Belum diklasifikasi';
                    } else {
                        $category = $ticket->sarprasDetail?->sarprasCategory?->name ?? 'Belum diklasifikasi';
                    }
                    $categories[$service->value][$category] = ($categories[$service->value][$category] ?? 0) + 1;

                    if ($ticket->status === TicketStatus::Ditolak) {
                        $rejected++;
                    }

                    if (! in_array($ticket->status, [TicketStatus::Terselesaikan, TicketStatus::Ditutup], true)) {
                        continue;
                    }

                    $completed++;
                    if ($ticket->completed_at !== null) {
                        $durationSeconds += $ticket->created_at->diffInSeconds($ticket->completed_at);
                        $durationCount++;
                    }
                    if ($ticket->completed_at !== null && $ticket->sla_deadline !== null) {
                        $sla[$ticket->completed_at <= $ticket->sla_deadline ? 'tercapai' : 'terlambat']++;
                    }
                }
            }, 'tickets.id', 'id');

        $monthChart = [];
        foreach ($months as $name => $count) {
            $monthChart[] = ['bulan' => $name, 'jumlah' => $count];
        }

        $evaluatedSla = array_sum($sla);

        return [
            'summary' => [
                'jumlah_tiket_masuk' => array_sum($status),
                'jumlah_tiket_selesai' => $completed,
                'jumlah_tiket_ditolak' => $rejected,
                'rata_rata_waktu_penyelesaian' => $durationCount > 0 ? round($durationSeconds / $durationCount / 3600, 2) : 0,
                'persentase_sla_tercapai' => $evaluatedSla > 0 ? round($sla['tercapai'] / $evaluatedSla * 100, 2) : 0,
            ],
            'charts' => [
                'tiket_per_bulan' => $monthChart,
                'status_tiket' => $status,
                'tiket_per_kategori' => [
                    'sarpras' => $this->categoryRows($categories['sarpras']),
                    'tik' => $this->categoryRows($categories['tik']),
                ],
                'sla' => $sla,
            ],
        ];
    }

    /** @param array<string, int> $counts */
    private function categoryRows(array $counts): array
    {
        arsort($counts);

        $rows = [];
        foreach ($counts as $category => $count) {
            $rows[] = ['kategori' => $category, 'jumlah' => $count];
        }

        return $rows;
    }
}
