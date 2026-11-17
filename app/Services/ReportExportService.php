<?php

namespace App\Services;

use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExportService
{
    /**
     * Charts contain trusted, server-generated SVG markup, never raw client input.
     *
     * @param  array{title: string, filters?: array<string, scalar|null>, summary?: array<string, scalar|null>, charts?: array<int, array{title: string, svg: string}>}  $report
     * @param  array{paper?: string, landscape?: bool, margins?: array{top: float, right: float, bottom: float, left: float}}  $page
     */
    public function pdf(array $report, string $filename, array $page = []): Response
    {
        $html = view('reports.pdf', $report)->render();
        $margins = $page['margins'] ?? ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15];

        $pdf = ConfiguredBrowsershot::html($html)
            ->format($page['paper'] ?? 'A4')
            ->landscape($page['landscape'] ?? false)
            ->margins($margins['top'], $margins['right'], $margins['bottom'], $margins['left'])
            ->showBackground()
            ->pdf();

        return $this->download($pdf, $filename, 'pdf', 'application/pdf');
    }

    /**
     * Rows are positional and match the order of headers. Pass codes as strings.
     *
     * @param  list<string>  $headers
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    public function xlsx(array $headers, iterable $rows, string $filename, string $worksheet = 'Report'): Response
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($worksheet);

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValueExplicit($column.'1', $header, DataType::TYPE_STRING);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        if ($headers !== []) {
            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            ]);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:'.$lastColumn.'1');
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
                } elseif ($value !== null) {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
            $rowNumber++;
        }

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open temporary report stream.');
        }

        try {
            (new Xlsx($spreadsheet))->save($stream);
            rewind($stream);
            $content = stream_get_contents($stream);
            if ($content === false) {
                throw new \RuntimeException('Unable to read temporary report stream.');
            }
        } finally {
            fclose($stream);
            $spreadsheet->disconnectWorksheets();
        }

        return $this->download($content, $filename, 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function download(string $content, string $filename, string $extension, string $mime): Response
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', pathinfo($filename, PATHINFO_FILENAME)) ?: 'report';

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$name.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
