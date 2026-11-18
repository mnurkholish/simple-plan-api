<?php

namespace App\Services;

use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExportService
{
    /**
     * Charts contain trusted, server-generated SVG markup, never raw client input.
     *
     * @param  array<string, mixed>  $report
     * @param  array{
     *     paper?: string,
     *     landscape?: bool,
     *     margins?: array{top: float, right: float, bottom: float, left: float},
     *     header_html?: string,
     *     footer_html?: string
     * }  $page
     */
    public function pdf(array $report, string $filename, array $page = [], string $view = 'reports.pdf'): Response
    {
        $html = view($view, $report)->render();
        $margins = $page['margins'] ?? ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15];

        $browsershot = ConfiguredBrowsershot::html($html)
            ->format($page['paper'] ?? 'A4')
            ->landscape($page['landscape'] ?? false)
            ->margins($margins['top'], $margins['right'], $margins['bottom'], $margins['left'])
            ->showBackground();

        if (! empty($page['footer_html']) || ! empty($page['header_html'])) {
            $browsershot
                ->showBrowserHeaderAndFooter()
                ->headerHtml($page['header_html'] ?? '<div></div>')
                ->footerHtml($page['footer_html'] ?? '<div></div>');
        }

        $pdf = $browsershot->pdf();

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
        return $this->xlsxMultiSheet([
            [
                'title' => $worksheet,
                'headers' => $headers,
                'rows' => $rows,
            ],
        ], $filename);
    }

    /**
     * Build a multi-sheet XLSX workbook where Row 1 on every sheet is directly the table header
     * (dark blue background, bold white text, frozen header row, AutoFilter) and Row 2+ are data rows.
     *
     * @param  list<array{title: string, headers: list<string>, rows: iterable<array<int, scalar|null>>}>  $sheets
     */
    public function xlsxMultiSheet(array $sheets, string $filename): Response
    {
        $spreadsheet = new Spreadsheet;
        $usedTitles = [];

        if ($sheets === []) {
            $sheets = [['title' => 'Semua Data', 'headers' => [], 'rows' => []]];
        }

        foreach ($sheets as $sheetIndex => $definition) {
            $sheet = $sheetIndex === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();

            $this->populateWorksheet(
                $sheet,
                $this->sanitizeSheetTitle($definition['title'], $usedTitles),
                $definition['headers'],
                $definition['rows'],
            );
        }

        $spreadsheet->setActiveSheetIndex(0);

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

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    private function populateWorksheet(Worksheet $sheet, string $title, array $headers, iterable $rows): void
    {
        $sheet->setTitle($title);

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValueExplicit($column.'1', $header, DataType::TYPE_STRING);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        if ($headers !== []) {
            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00338D']],
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
    }

    /**
     * Sanitize an Excel worksheet title (max 31 chars, no : \ / ? * [ ], unique case-insensitively).
     *
     * @param  array<string, true>  $usedTitles
     */
    private function sanitizeSheetTitle(string $title, array &$usedTitles): string
    {
        $cleaned = preg_replace('/[:\\\\\/\?\*\[\]]+/', ' ', $title) ?? '';
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? '', " \t\n\r\0\x0B'");

        if ($cleaned === '') {
            $cleaned = 'Sheet';
        }

        $base = mb_substr($cleaned, 0, 31, 'UTF-8');
        $candidate = $base;
        $counter = 2;

        while (isset($usedTitles[mb_strtolower($candidate, 'UTF-8')])) {
            $suffix = ' ('.$counter.')';
            $maxBaseLength = max(1, 31 - mb_strlen($suffix, 'UTF-8'));
            $candidate = rtrim(mb_substr($cleaned, 0, $maxBaseLength, 'UTF-8')).$suffix;
            $counter++;
        }

        $usedTitles[mb_strtolower($candidate, 'UTF-8')] = true;

        return $candidate;
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
