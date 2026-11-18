<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpdeskReportExportRequest;
use App\Http\Requests\HelpdeskReportRequest;
use App\Models\User;
use App\Services\HelpdeskReportService;
use App\Services\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class HelpdeskReportController extends Controller
{
    public function __construct(private readonly HelpdeskReportService $service) {}

    public function index(HelpdeskReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->report($request->validated(), $request->user())]);
    }

    public function export(HelpdeskReportExportRequest $request, ReportExportService $export): Response
    {
        $filters = $request->safe()->except('format');
        $report = $this->service->report($filters, $request->user());
        $filename = 'laporan-helpdesk-'.$filters['periode_awal'].'-'.$filters['periode_akhir'];

        if ($request->validated('format') === 'pdf') {
            $service = $request->user()->hasRole('koordinator-sarpras')
                ? 'Sarpras'
                : match ($filters['jenis_layanan'] ?? 'semua') {
                    'tik' => 'TIK', 'sarpras' => 'Sarpras', default => 'Semua',
                };

            return $export->pdf([
                'summary' => $report['summary'],
                'charts' => $report['charts'],
                'period' => $filters['periode_awal'].' – '.$filters['periode_akhir'],
                'service' => $service,
                'downloadedAt' => now()->format('d M Y, H:i'),
                'downloadedBy' => $request->user()->name,
            ], $filename.'.pdf', [
                'view' => 'reports.helpdesk.pdf',
                'paper' => 'A4',
                'landscape' => false,
                'margins' => ['top' => 15, 'right' => 12, 'bottom' => 18, 'left' => 12],
                'footer' => '<div style="width:100%;margin:0 12mm;border-top:1px solid #dbe5f0;padding-top:7px;color:#90a0b8;font:9px Arial;text-align:right">Halaman <span class="pageNumber"></span> dari <span class="totalPages"></span></div>',
            ]);
        }

        return $export->xlsxMultiSheet($this->sheets($report, $filters, $request->user()), $filename.'.xlsx');
    }

    /** @param array<string, mixed> $report @param array<string, string> $filters */
    private function sheets(array $report, array $filters, User $actor): array
    {
        $summary = $report['summary'];
        $charts = $report['charts'];
        $categories = $charts['tiket_per_kategori'];
        $categoryRows = [];
        foreach (['sarpras' => 'Sarpras', 'tik' => 'TIK'] as $key => $label) {
            foreach ($categories[$key] as $row) {
                $categoryRows[] = [$label, $row['kategori'], $row['jumlah']];
            }
        }

        return [
            [
                'name' => 'Rincian Tiket',
                'headers' => ['Nomor Tiket', 'Jenis Layanan', 'Tanggal Dibuat', 'Nomor Inventaris', 'Nama Aset', 'Kategori', 'Prioritas', 'Petugas', 'Status', 'Batas Waktu SLA', 'Waktu Selesai Perbaikan', 'Status SLA'],
                'rows' => $this->service->detailRows($filters, $actor),
            ],
            [
                'name' => 'Rekap',
                'headers' => ['Indikator', 'Nilai'],
                'rows' => [
                    ['Jumlah tiket masuk', $summary['jumlah_tiket_masuk']],
                    ['Jumlah tiket selesai', $summary['jumlah_tiket_selesai']],
                    ['Jumlah tiket ditolak', $summary['jumlah_tiket_ditolak']],
                    ['Rata-rata waktu penyelesaian (jam)', $summary['rata_rata_waktu_penyelesaian']],
                    ['Persentase SLA tercapai (%)', $summary['persentase_sla_tercapai']],
                ],
            ],
            ['name' => 'Tiket per Bulan', 'headers' => ['Bulan', 'Jumlah Tiket'], 'rows' => array_map(fn (array $row): array => [$row['bulan'], $row['jumlah']], $charts['tiket_per_bulan'])],
            ['name' => 'Status Tiket', 'headers' => ['Status', 'Jumlah Tiket'], 'rows' => array_map(fn (string $key, int $count): array => [ucfirst($key), $count], array_keys($charts['status_tiket']), array_values($charts['status_tiket']))],
            ['name' => 'Kategori Tiket', 'headers' => ['Jenis Layanan', 'Kategori', 'Jumlah Tiket'], 'rows' => $categoryRows],
            ['name' => 'SLA', 'headers' => ['Status SLA', 'Jumlah Tiket'], 'rows' => [['Tercapai', $charts['sla']['tercapai']], ['Terlambat', $charts['sla']['terlambat']]]],
        ];
    }
}
