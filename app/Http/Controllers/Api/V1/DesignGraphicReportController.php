<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DesignGraphicReportExportRequest;
use App\Http\Requests\DesignGraphicReportRequest;
use App\Models\User;
use App\Services\DesignGraphicReportService;
use App\Services\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DesignGraphicReportController extends Controller
{
    public function __construct(private readonly DesignGraphicReportService $service) {}

    public function index(DesignGraphicReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->report($request->validated(), $request->user())]);
    }

    public function export(DesignGraphicReportExportRequest $request, ReportExportService $export): Response
    {
        $filters = $request->safe()->except('format');
        $report = $this->service->report($filters, $request->user());
        $filename = 'laporan-desain-grafis-'.$filters['periode_awal'].'-'.$filters['periode_akhir'];

        if ($request->validated('format') === 'pdf') {
            return $export->pdf([
                'summary' => $report['summary'],
                'charts' => $report['charts'],
                'period' => $filters['periode_awal'].' – '.$filters['periode_akhir'],
                'downloadedAt' => now()->format('d M Y, H:i'),
                'downloadedBy' => $request->user()->name,
            ], $filename.'.pdf', [
                'view' => 'reports.design-graphic.pdf',
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

        return [
            [
                'name' => 'Rincian Request',
                'headers' => ['Nomor Request', 'Tanggal Dibuat', 'Pemohon', 'Unit', 'Jenis Desain', 'Petugas Desain', 'Status', 'Tanggal Selesai'],
                'rows' => $this->service->detailRows($filters, $actor),
            ],
            [
                'name' => 'Rekap',
                'headers' => ['Indikator', 'Nilai'],
                'rows' => [
                    ['Jumlah Request Masuk', $summary['jumlah_request_masuk']],
                    ['Jumlah Request Selesai', $summary['jumlah_request_selesai']],
                    ['Jumlah Request Ditolak', $summary['jumlah_request_ditolak']],
                    ['Rata-rata Waktu Penyelesaian (hari)', $summary['rata_rata_waktu_penyelesaian']],
                ],
            ],
            ['name' => 'Request per Bulan', 'headers' => ['Bulan', 'Jumlah Request'], 'rows' => array_map(fn (array $row): array => [$row['bulan'], $row['jumlah']], $charts['request_per_bulan'])],
            ['name' => 'Status Request', 'headers' => ['Status', 'Jumlah Request'], 'rows' => array_map(fn (string $key, int $count): array => [ucwords(str_replace('_', ' ', $key)), $count], array_keys($charts['status_request']), array_values($charts['status_request']))],
            ['name' => 'Jenis Desain', 'headers' => ['Jenis Desain', 'Jumlah Request'], 'rows' => array_map(fn (array $row): array => [$row['jenis_desain'], $row['jumlah']], $charts['request_per_jenis_desain'])],
        ];
    }
}
