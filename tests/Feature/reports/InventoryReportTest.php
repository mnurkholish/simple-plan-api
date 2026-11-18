<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReportExportService;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CorePermissionSeeder::class, DomainPermissionSeeder::class, RoleSeeder::class]);
});

function inventoryReportUrl(array $filters = []): string
{
    $query = http_build_query($filters);

    return '/api/v1/reports/inventory'.($query !== '' ? '?'.$query : '');
}

function inventoryReportExportUrl(array $filters = []): string
{
    $query = http_build_query($filters);

    return '/api/v1/reports/inventory/export'.($query !== '' ? '?'.$query : '');
}

function actAsInventoryReportRole(string $role): User
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole(Role::findByName($role, 'web'));
    Sanctum::actingAs($user);

    return $user;
}

/** @param array<string, mixed> $attributes */
function createInventoryReportAsset(
    AssetClassification $classification,
    AssetLocation $location,
    AssetItem $item,
    array $attributes = [],
    ?string $fullCode = null,
): Asset {
    static $sequence = 1;
    $currentSequence = $sequence++;

    $code = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $item->id,
        'sequence_number' => $currentSequence,
        'full_code' => $fullCode ?? sprintf('%02d.%02d.%02d.%04d', $classification->code, $location->code, $item->code, $currentSequence),
    ]);

    return Asset::create([
        'asset_code_id' => $code->id,
        'detail_item' => $attributes['detail_item'] ?? null,
        'serial_number' => 'SN-INV-'.$currentSequence,
        'purchase_year' => 2026,
        'status' => AssetStatus::InUse,
        'condition' => AssetCondition::Good,
        ...$attributes,
    ]);
}

/**
 * @return array{
 *     sheet_names: list<string>,
 *     sheets: array<string, array{
 *         headers: list<mixed>,
 *         rows: list<array<int, mixed>>,
 *         types: list<array<int, string>>,
 *         auto_filter: string
 *     }>
 * }
 */
function parseInventoryXlsxWorkbook(string $binary): array
{
    $tempPath = tempnam(sys_get_temp_dir(), 'inv-report-');
    expect($tempPath)->not->toBeFalse();
    file_put_contents($tempPath, $binary);

    try {
        $spreadsheet = IOFactory::load($tempPath);
        $sheetNames = [];
        $sheets = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = $sheet->getTitle();
            $sheetNames[] = $title;

            $highestRow = $sheet->getHighestRow();
            $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

            $headers = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col).'1');
                $headers[] = $cell->getValue();
            }

            $rows = [];
            $types = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $rowTypes = [];
                for ($col = 1; $col <= $highestColIndex; $col++) {
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col).$row);
                    $rowData[] = $cell->getValue();
                    $rowTypes[] = $cell->getDataType();
                }
                $rows[] = $rowData;
                $types[] = $rowTypes;
            }

            $sheets[$title] = [
                'headers' => $headers,
                'rows' => $rows,
                'types' => $types,
                'auto_filter' => (string) $sheet->getAutoFilter()->getRange(),
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return ['sheet_names' => $sheetNames, 'sheets' => $sheets];
    } finally {
        @unlink($tempPath);
    }
}

test('laporan inventaris menghitung rekap, grafik klasifikasi, kondisi, dan seluruh lokasi tanpa limit', function (): void {
    actAsInventoryReportRole('super-admin');

    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatMedis = AssetClassification::factory()->create(['name' => 'Alat Medis']);
    $alatSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras']);

    $itemIt = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Laptop']);
    $itemMedis = AssetItem::factory()->create(['asset_classification_id' => $alatMedis->id, 'name' => 'Monitor Pasien']);
    $itemSarpras = AssetItem::factory()->create(['asset_classification_id' => $alatSarpras->id, 'name' => 'AC']);

    // Buat 12 lokasi untuk memastikan tidak ada limit 10 (mendukung "Show more" di UI)
    $locations = [];
    for ($i = 1; $i <= 12; $i++) {
        $locations[$i] = AssetLocation::factory()->create(['name' => sprintf('Ruang %02d', $i)]);
    }

    // Lokasi 1: 3 aset (Alat IT: 2, Alat Medis: 1)
    createInventoryReportAsset($alatIt, $locations[1], $itemIt, ['status' => AssetStatus::InUse, 'condition' => AssetCondition::Good]);
    createInventoryReportAsset($alatIt, $locations[1], $itemIt, ['status' => AssetStatus::Damaged, 'condition' => AssetCondition::HeavyDamage]);
    createInventoryReportAsset($alatMedis, $locations[1], $itemMedis, ['status' => AssetStatus::Borrowed, 'condition' => AssetCondition::LightDamage]);

    // Lokasi 2: 2 aset
    createInventoryReportAsset($alatSarpras, $locations[2], $itemSarpras, ['status' => AssetStatus::Damaged, 'condition' => AssetCondition::LightDamage]);
    createInventoryReportAsset($alatMedis, $locations[2], $itemMedis, ['status' => AssetStatus::Borrowed, 'condition' => AssetCondition::Good]);

    // Lokasi 3..12: masing-masing 1 aset (total 10 aset)
    for ($i = 3; $i <= 12; $i++) {
        createInventoryReportAsset($alatSarpras, $locations[$i], $itemSarpras, ['status' => AssetStatus::NotInUse, 'condition' => AssetCondition::Good]);
    }

    // Soft-deleted asset tidak boleh dihitung
    $deletedAsset = createInventoryReportAsset($alatIt, $locations[1], $itemIt, ['status' => AssetStatus::Damaged, 'condition' => AssetCondition::HeavyDamage]);
    $deletedAsset->delete();

    $response = $this->getJson(inventoryReportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'total_aset' => 15,
            'total_rusak' => 2,
            'total_dipinjam' => 2,
        ])
        ->assertJsonPath('data.charts.per_kondisi', [
            'Baik' => 12,
            'Rusak Ringan' => 2,
            'Rusak Berat' => 1,
        ])
        ->assertJsonPath('data.charts.per_klasifikasi', [
            ['klasifikasi' => 'Alat Sarpras', 'jumlah' => 11],
            ['klasifikasi' => 'Alat IT', 'jumlah' => 2],
            ['klasifikasi' => 'Alat Medis', 'jumlah' => 2],
        ]);

    $perLokasi = $response->json('data.charts.per_lokasi');
    expect($perLokasi)->toHaveCount(12)
        ->and($perLokasi[0])->toBe(['lokasi' => 'Ruang 01', 'jumlah' => 3])
        ->and($perLokasi[1])->toBe(['lokasi' => 'Ruang 02', 'jumlah' => 2])
        ->and($perLokasi[11])->toBe(['lokasi' => 'Ruang 12', 'jumlah' => 1]);
});

test('filter gabungan classification_id, status, dan location_id bekerja serta menolak input tidak valid', function (): void {
    actAsInventoryReportRole('management');

    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatMedis = AssetClassification::factory()->create(['name' => 'Alat Medis']);
    $igd = AssetLocation::factory()->create(['name' => 'IGD']);
    $icu = AssetLocation::factory()->create(['name' => 'ICU']);

    $laptop = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Laptop']);
    $ekg = AssetItem::factory()->create(['asset_classification_id' => $alatMedis->id, 'name' => 'EKG']);

    createInventoryReportAsset($alatIt, $igd, $laptop, ['status' => AssetStatus::Damaged, 'condition' => AssetCondition::HeavyDamage]);
    createInventoryReportAsset($alatIt, $igd, $laptop, ['status' => AssetStatus::InUse, 'condition' => AssetCondition::Good]);
    createInventoryReportAsset($alatIt, $icu, $laptop, ['status' => AssetStatus::Damaged, 'condition' => AssetCondition::LightDamage]);
    createInventoryReportAsset($alatMedis, $igd, $ekg, ['status' => AssetStatus::Damaged, 'condition' => AssetCondition::HeavyDamage]);

    // Filter menggunakan classification_id, location_id, status
    $this->getJson(inventoryReportUrl([
        'classification_id' => $alatIt->id,
        'location_id' => $igd->id,
        'status' => AssetStatus::Damaged->value,
    ]))->assertOk()
        ->assertJsonPath('data.summary', [
            'total_aset' => 1,
            'total_rusak' => 1,
            'total_dipinjam' => 0,
        ])
        ->assertJsonPath('data.charts.per_klasifikasi', [
            ['klasifikasi' => 'Alat IT', 'jumlah' => 1],
        ])
        ->assertJsonPath('data.charts.per_lokasi', [
            ['lokasi' => 'IGD', 'jumlah' => 1],
        ])
        ->assertJsonPath('data.charts.per_kondisi', [
            'Baik' => 0,
            'Rusak Ringan' => 0,
            'Rusak Berat' => 1,
        ]);

    // Filter klasifikasi Alat Medis + lokasi IGD
    $this->getJson(inventoryReportUrl([
        'classification_id' => $alatMedis->id,
        'location_id' => $igd->id,
        'status' => AssetStatus::Damaged->value,
    ]))->assertOk()
        ->assertJsonPath('data.summary.total_aset', 1)
        ->assertJsonPath('data.charts.per_klasifikasi', [
            ['klasifikasi' => 'Alat Medis', 'jumlah' => 1],
        ]);

    // Validasi filter tidak valid mengembalikan 422
    foreach ([
        '?classification_id=999999',
        '?classification_id=bukan-angka',
        '?location_id=999999',
        '?location_id=bukan-angka',
        '?status=StatusTidakAda',
    ] as $query) {
        $this->getJson('/api/v1/reports/inventory'.$query)->assertUnprocessable();
    }

    // Validasi format export tidak valid mengembalikan 422
    foreach (['', '?format=csv', '?format=doc'] as $query) {
        $this->getJson('/api/v1/reports/inventory/export'.$query)->assertUnprocessable();
    }
});

test('super-admin dan management dapat mengakses seluruh klasifikasi termasuk Alat IT', function (): void {
    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras']);
    $ruangIt = AssetLocation::factory()->create(['name' => 'Ruang IT']);
    $server = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Server']);
    $ac = AssetItem::factory()->create(['asset_classification_id' => $alatSarpras->id, 'name' => 'AC']);

    createInventoryReportAsset($alatIt, $ruangIt, $server, ['status' => AssetStatus::Damaged, 'condition' => AssetCondition::HeavyDamage]);
    createInventoryReportAsset($alatSarpras, $ruangIt, $ac, ['status' => AssetStatus::Borrowed, 'condition' => AssetCondition::Good]);

    foreach (['super-admin', 'management'] as $role) {
        actAsInventoryReportRole($role);

        $this->getJson(inventoryReportUrl())->assertOk()
            ->assertJsonPath('data.summary', [
                'total_aset' => 2,
                'total_rusak' => 1,
                'total_dipinjam' => 1,
            ])
            ->assertJsonPath('data.charts.per_klasifikasi', [
                ['klasifikasi' => 'Alat IT', 'jumlah' => 1],
                ['klasifikasi' => 'Alat Sarpras', 'jumlah' => 1],
            ]);
    }
});

test('koordinator-sarpras dan koordinator_sarpras mengecualikan klasifikasi Alat IT pada laporan JSON, PDF, dan seluruh sheet XLSX', function (): void {
    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras']);
    $alatMedis = AssetClassification::factory()->create(['name' => 'Alat Medis']);
    $ruangUmum = AssetLocation::factory()->create(['name' => 'Ruang Umum']);

    $server = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Server']);
    $ac = AssetItem::factory()->create(['asset_classification_id' => $alatSarpras->id, 'name' => 'AC']);
    $tensimeter = AssetItem::factory()->create(['asset_classification_id' => $alatMedis->id, 'name' => 'Tensimeter']);

    createInventoryReportAsset($alatIt, $ruangUmum, $server, [
        'status' => AssetStatus::Damaged,
        'condition' => AssetCondition::HeavyDamage,
    ], '0601010001');
    createInventoryReportAsset($alatSarpras, $ruangUmum, $ac, [
        'detail_item' => '2 PK',
        'status' => AssetStatus::Borrowed,
        'condition' => AssetCondition::LightDamage,
    ], '0301010002');
    createInventoryReportAsset($alatMedis, $ruangUmum, $tensimeter, [
        'status' => AssetStatus::InUse,
        'condition' => AssetCondition::Good,
    ], '0401010003');

    // Uji koordinator-sarpras
    actAsInventoryReportRole('koordinator-sarpras');

    $this->getJson(inventoryReportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'total_aset' => 2,
            'total_rusak' => 0,
            'total_dipinjam' => 1,
        ])
        ->assertJsonPath('data.charts.per_klasifikasi', [
            ['klasifikasi' => 'Alat Medis', 'jumlah' => 1],
            ['klasifikasi' => 'Alat Sarpras', 'jumlah' => 1],
        ]);

    // Sekalipun memaksa filter classification_id=Alat IT, tetap menghasilkan 0
    $this->getJson(inventoryReportUrl(['classification_id' => $alatIt->id]))->assertOk()
        ->assertJsonPath('data.summary', [
            'total_aset' => 0,
            'total_rusak' => 0,
            'total_dipinjam' => 0,
        ])
        ->assertJsonPath('data.charts.per_klasifikasi', [])
        ->assertJsonPath('data.charts.per_lokasi', []);

    // Export XLSX untuk koordinator-sarpras: Sheet 1 Semua Data + Sheet Alat Medis + Sheet Alat Sarpras (tanpa Alat IT)
    $xlsxResponse = $this->get(inventoryReportExportUrl(['format' => 'xlsx']))->assertOk();
    $workbook = parseInventoryXlsxWorkbook($xlsxResponse->getContent());

    expect($workbook['sheet_names'])->toBe(['Semua Data', 'Alat Medis', 'Alat Sarpras'])
        ->and($workbook['sheets']['Semua Data']['rows'])->toHaveCount(2)
        ->and(array_column($workbook['sheets']['Semua Data']['rows'], 3))->not->toContain('Alat IT')
        ->toContain('Alat Sarpras', 'Alat Medis')
        ->and($workbook['sheets']['Alat Medis']['rows'])->toHaveCount(1)
        ->and($workbook['sheets']['Alat Sarpras']['rows'])->toHaveCount(1);

    // Uji alias role snake_case koordinator_sarpras
    $snakeRole = Role::firstOrCreate(['name' => 'koordinator_sarpras', 'guard_name' => 'web']);
    $snakeRole->givePermissionTo(['inventory-reports-read', 'inventory-reports-export']);
    actAsInventoryReportRole('koordinator_sarpras');

    $this->getJson(inventoryReportUrl())->assertOk()
        ->assertJsonPath('data.summary.total_aset', 2);
});

test('export xlsx menghasilkan multi-sheet dengan baris 1 langsung header tabel, AutoFilter, dan kode bertipe string', function (): void {
    actAsInventoryReportRole('super-admin');

    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatKhusus = AssetClassification::factory()->create(['name' => 'Alat: Medis / Lab? *Khusus* [RSCH] Panjang Sekali']);
    $ruangIt = AssetLocation::factory()->create(['name' => 'Ruang IT']);
    $laptop = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Laptop']);
    $analyzer = AssetItem::factory()->create(['asset_classification_id' => $alatKhusus->id, 'name' => 'Analyzer']);

    createInventoryReportAsset($alatIt, $ruangIt, $laptop, [
        'detail_item' => 'ThinkPad X1',
        'status' => AssetStatus::Damaged,
        'condition' => AssetCondition::HeavyDamage,
    ], '0602010001');

    createInventoryReportAsset($alatKhusus, $ruangIt, $analyzer, [
        'detail_item' => 'Hematology',
        'status' => AssetStatus::InUse,
        'condition' => AssetCondition::Good,
    ], '0402010002');

    $response = $this->get(inventoryReportExportUrl(['format' => 'xlsx']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="laporan-inventaris.xlsx"');

    $workbook = parseInventoryXlsxWorkbook($response->getContent());

    $expectedHeaders = [
        'No. Inventaris',
        'Kode Aset',
        'Nama Aset',
        'Klasifikasi',
        'Lokasi Ruangan',
        'Status',
        'Kondisi',
    ];

    expect($workbook['sheet_names'])->toHaveCount(3)
        ->and($workbook['sheet_names'][0])->toBe('Semua Data')
        ->and($workbook['sheet_names'])->toContain('Alat IT');

    // Nama sheet klasifikasi khusus harus ter-sanitasi (<= 31 karakter dan bebas karakter terlarang)
    $otherSheets = array_values(array_diff($workbook['sheet_names'], ['Semua Data', 'Alat IT']));
    expect($otherSheets)->toHaveCount(1);
    $sanitizedSheetName = $otherSheets[0];
    expect(mb_strlen($sanitizedSheetName, 'UTF-8'))->toBeLessThanOrEqual(31)
        ->and($sanitizedSheetName)->not->toMatch('/[:\\\\\/\?\*\[\]]/');

    // Row 1 di semua sheet langsung header tabel + AutoFilter A1:G1
    foreach ($workbook['sheet_names'] as $sheetName) {
        expect($workbook['sheets'][$sheetName]['headers'])->toBe($expectedHeaders)
            ->and($workbook['sheets'][$sheetName]['auto_filter'])->toBe('A1:G1');
    }

    // Sheet 1 (Semua Data) memuat gabungan semua baris
    expect($workbook['sheets']['Semua Data']['rows'])->toBe([
        [
            '0602010001',
            '0602010001',
            'Laptop ThinkPad X1',
            'Alat IT',
            'Ruang IT',
            'Rusak',
            'Rusak Berat',
        ],
        [
            '0402010002',
            '0402010002',
            'Analyzer Hematology',
            'Alat: Medis / Lab? *Khusus* [RSCH] Panjang Sekali',
            'Ruang IT',
            'Digunakan',
            'Baik',
        ],
    ]);

    // Sheet per klasifikasi hanya memuat baris klasifikasi terkait
    expect($workbook['sheets']['Alat IT']['rows'])->toHaveCount(1)
        ->and($workbook['sheets'][$sanitizedSheetName]['rows'])->toHaveCount(1);

    // Pastikan No. Inventaris (kolom 0) dan Kode Aset (kolom 1) bertipe string di Excel
    expect($workbook['sheets']['Semua Data']['types'][0][0])->toBe(DataType::TYPE_STRING)
        ->and($workbook['sheets']['Semua Data']['types'][0][1])->toBe(DataType::TYPE_STRING)
        ->and($workbook['sheets']['Alat IT']['types'][0][0])->toBe(DataType::TYPE_STRING)
        ->and($workbook['sheets']['Alat IT']['types'][0][1])->toBe(DataType::TYPE_STRING);
});

test('export pdf menggunakan template khusus reports.inventory.pdf dengan donut SVG, metadata pengunduh, dan file PDF valid', function (): void {
    $admin = actAsInventoryReportRole('super-admin');

    $classification = AssetClassification::factory()->create(['name' => 'Alat <script>alert(1)</script> & Medis']);
    $location = AssetLocation::factory()->create(['name' => 'IGD & ICU "Utama"']);
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id, 'name' => 'Monitor']);

    createInventoryReportAsset($classification, $location, $item, [
        'status' => AssetStatus::Damaged,
        'condition' => AssetCondition::HeavyDamage,
    ]);

    $capturedPayload = null;
    $capturedView = null;
    $capturedPage = null;

    $spyExporter = new class($capturedPayload, $capturedView, $capturedPage) extends ReportExportService
    {
        public function __construct(
            private mixed &$payload,
            private mixed &$view,
            private mixed &$page,
        ) {}

        public function pdf(array $report, string $filename, array $page = [], ?string $view = null): Response
        {
            $this->payload = $report;
            $this->view = $view ?? $page['view'] ?? 'reports.pdf';
            $this->page = $page;

            return parent::pdf($report, $filename, $page, $view);
        }
    };
    app()->instance(ReportExportService::class, $spyExporter);

    $response = $this->get(inventoryReportExportUrl([
        'format' => 'pdf',
        'classification_id' => $classification->id,
    ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="laporan-inventaris.pdf"');

    $pdf = $response->getContent();
    expect($pdf)->toMatch('/^%PDF-\d\.\d/')
        ->toContain('startxref', '%%EOF');

    expect($capturedView)->toBe('reports.inventory.pdf')
        ->and($capturedPage['footer_html'] ?? '')->toContain('Halaman', 'pageNumber', 'totalPages')
        ->and($capturedPayload)->toBeArray()
        ->toHaveKeys(['title', 'subtitle', 'downloaded_at', 'filters', 'summary', 'charts'])
        ->and($capturedPayload['title'])->toBe('Laporan Inventaris')
        ->and($capturedPayload['subtitle'])->toBe('RS Citra Husada Jember')
        ->and($capturedPayload['downloaded_at'])->toContain($admin->name, 'Super Admin')
        ->and($capturedPayload['summary'])->toBe([
            'total_aset' => 1,
            'total_rusak' => 1,
            'total_dipinjam' => 0,
        ])
        ->and($capturedPayload['charts']['per_klasifikasi_donut_svg'])->toContain('<svg', '</svg>', 'Aset')
        ->not->toContain('<script>');
});

test('laporan dan export dengan data kosong mengembalikan nol, grafik kosong, dan file valid', function (): void {
    actAsInventoryReportRole('management');

    $this->getJson(inventoryReportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'total_aset' => 0,
            'total_rusak' => 0,
            'total_dipinjam' => 0,
        ])
        ->assertJsonPath('data.charts', [
            'per_klasifikasi' => [],
            'per_kondisi' => [
                'Baik' => 0,
                'Rusak Ringan' => 0,
                'Rusak Berat' => 0,
            ],
            'per_lokasi' => [],
        ]);

    $xlsxResponse = $this->get(inventoryReportExportUrl(['format' => 'xlsx']))->assertOk();
    $workbook = parseInventoryXlsxWorkbook($xlsxResponse->getContent());
    expect($workbook['sheet_names'])->toBe(['Semua Data'])
        ->and($workbook['sheets']['Semua Data']['headers'])->toBe([
            'No. Inventaris',
            'Kode Aset',
            'Nama Aset',
            'Klasifikasi',
            'Lokasi Ruangan',
            'Status',
            'Kondisi',
        ])->and($workbook['sheets']['Semua Data']['rows'])->toBe([]);

    $pdfResponse = $this->get(inventoryReportExportUrl(['format' => 'pdf']))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($pdfResponse->getContent())->toMatch('/^%PDF-\d\.\d/');
});

test('guest dan role tanpa izin tidak dapat mengakses laporan maupun export inventaris', function (): void {
    expect(route('api.v1.reports.inventory.index', [], false))->toBe('/api/v1/reports/inventory')
        ->and(route('api.v1.reports.inventory.export', [], false))->toBe('/api/v1/reports/inventory/export');

    $this->getJson(inventoryReportUrl())->assertUnauthorized();
    $this->getJson(inventoryReportExportUrl(['format' => 'xlsx']))->assertUnauthorized();

    foreach (['user', 'petugas-tik', 'petugas-sarpras'] as $role) {
        $user = actAsInventoryReportRole($role);
        $this->getJson(inventoryReportUrl())->assertForbidden();
        $this->getJson(inventoryReportExportUrl(['format' => 'xlsx']))->assertForbidden();

        if ($role === 'user') {
            $user->givePermissionTo(['inventory-reports-read', 'inventory-reports-export']);
            $this->getJson(inventoryReportUrl())->assertForbidden();
            $this->getJson(inventoryReportExportUrl(['format' => 'pdf']))->assertForbidden();
        }
    }

    // Role yang diizinkan tanpa permission export tidak dapat mengakses endpoint export
    $roleWithoutExport = Role::firstOrCreate(['name' => 'koordinator_sarpras', 'guard_name' => 'web']);
    $roleWithoutExport->syncPermissions(['inventory-reports-read']);
    actAsInventoryReportRole('koordinator_sarpras');

    $this->getJson(inventoryReportUrl())->assertOk();
    $this->getJson(inventoryReportExportUrl(['format' => 'xlsx']))->assertForbidden();
});
