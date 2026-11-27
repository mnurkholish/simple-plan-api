<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceLog;
use App\Models\AssetMaintenanceOccurrence;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReportExportService;
use Carbon\Carbon;
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
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00'));
    $this->seed([CorePermissionSeeder::class, DomainPermissionSeeder::class, RoleSeeder::class]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function maintenanceReportUrl(array $filters = []): string
{
    $query = http_build_query($filters);

    return '/api/v1/reports/maintenance'.($query !== '' ? '?'.$query : '');
}

function maintenanceReportExportUrl(array $filters = []): string
{
    $query = http_build_query($filters);

    return '/api/v1/reports/maintenance/export'.($query !== '' ? '?'.$query : '');
}

function actAsMaintenanceReportRole(string $role): User
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole(Role::findByName($role, 'web'));
    Sanctum::actingAs($user);

    return $user;
}

/**
 * @param  array<string, mixed>  $occurrenceAttributes
 * @param  array<string, mixed>  $assetAttributes
 */
function createMaintenanceReportOccurrence(
    AssetClassification $classification,
    AssetLocation $location,
    AssetItem $item,
    User $officer,
    User $creator,
    array $occurrenceAttributes = [],
    ?string $fullCode = null,
    array $assetAttributes = [],
): AssetMaintenanceOccurrence {
    static $sequence = 1;
    $currentSequence = $sequence++;

    $code = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $item->id,
        'sequence_number' => $currentSequence,
        'full_code' => $fullCode ?? sprintf('%02d%02d%02d%04d', $classification->code, $location->code, $item->code, $currentSequence),
    ]);

    Asset::create([
        'asset_code_id' => $code->id,
        'detail_item' => $assetAttributes['detail_item'] ?? null,
        'serial_number' => 'SN-MNT-'.$currentSequence,
        'purchase_year' => 2026,
        'status' => AssetStatus::InUse,
        'condition' => AssetCondition::Good,
        ...$assetAttributes,
    ]);

    $period = $occurrenceAttributes['period'] ?? MaintenancePeriod::Monthly;
    $scheduledDate = $occurrenceAttributes['scheduled_date'] ?? '2026-11-25';
    $status = $occurrenceAttributes['status'] ?? MaintenanceStatus::Terjadwal;

    $maintenance = AssetMaintenance::create([
        'asset_code_id' => $code->id,
        'assigned_officer_id' => $officer->id,
        'creator_id' => $creator->id,
        'maintenance_type' => 'Pemeliharaan Berkala '.$currentSequence,
        'period' => $period,
        'next_scheduled_date' => $scheduledDate,
        'status' => $status,
        'is_active' => true,
    ]);

    $postCondition = $occurrenceAttributes['post_maintenance_condition'] ?? AssetCondition::Good;
    $logId = $occurrenceAttributes['asset_maintenance_log_id'] ?? null;
    if (($occurrenceAttributes['with_log'] ?? false) === true && $logId === null) {
        $completedAt = $occurrenceAttributes['realized_at'] ?? "{$scheduledDate} 09:30:00";
        $log = AssetMaintenanceLog::create([
            'asset_maintenance_id' => $maintenance->id,
            'scheduled_date' => $scheduledDate,
            'work_description' => 'Pemeriksaan berkala',
            'maintenance_result' => 'Normal',
            'post_maintenance_condition' => $postCondition,
            'started_at' => Carbon::parse($completedAt)->subHour()->format('Y-m-d H:i:s'),
            'completed_at' => $completedAt,
        ]);
        $logId = $log->id;
    }

    unset($occurrenceAttributes['with_log'], $occurrenceAttributes['post_maintenance_condition']);

    return AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $maintenance->id,
        'asset_code_id' => $code->id,
        'asset_maintenance_log_id' => $logId,
        'period' => $period,
        'scheduled_date' => $scheduledDate,
        'status' => $status,
        'realized_at' => $occurrenceAttributes['realized_at'] ?? null,
        'is_late' => $occurrenceAttributes['is_late'] ?? false,
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
function parseMaintenanceXlsxWorkbook(string $binary): array
{
    $tempPath = tempnam(sys_get_temp_dir(), 'mnt-report-');
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

test('laporan maintenance menghasilkan struktur JSON sesuai UI dengan 12 bulan Jan-Des, kondisi pasca pemeliharaan, dan seluruh petugas tanpa limit', function (): void {
    $admin = actAsMaintenanceReportRole('super-admin');

    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatMedis = AssetClassification::factory()->create(['name' => 'Alat Medis']);
    $alatSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras']);
    $ruang = AssetLocation::factory()->create(['name' => 'Ruang Utama']);

    $server = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Server']);
    $ekg = AssetItem::factory()->create(['asset_classification_id' => $alatMedis->id, 'name' => 'EKG']);
    $ac = AssetItem::factory()->create(['asset_classification_id' => $alatSarpras->id, 'name' => 'AC']);

    // Buat 12 petugas untuk memastikan per_petugas tidak terkena limit 10
    $officers = [];
    for ($i = 1; $i <= 12; $i++) {
        $officers[$i] = User::factory()->for(Unit::factory())->create([
            'name' => sprintf('Petugas %02d', $i),
        ]);
    }

    // Petugas 01: 3 selesai (Jan Baik, Feb Rusak Ringan, Mar Rusak Berat)
    createMaintenanceReportOccurrence($alatIt, $ruang, $server, $officers[1], $admin, [
        'scheduled_date' => '2026-01-10',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-01-10 09:00:00',
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ]);
    createMaintenanceReportOccurrence($alatIt, $ruang, $server, $officers[1], $admin, [
        'scheduled_date' => '2026-02-15',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-02-17 10:00:00',
        'is_late' => true,
        'post_maintenance_condition' => AssetCondition::LightDamage,
        'with_log' => true,
    ]);
    createMaintenanceReportOccurrence($alatMedis, $ruang, $ekg, $officers[1], $admin, [
        'scheduled_date' => '2026-03-08',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-03-08 11:00:00',
        'post_maintenance_condition' => AssetCondition::HeavyDamage,
        'with_log' => true,
    ]);

    // Petugas 02: 2 selesai (Okt Baik, Nov Baik) + 1 terlambat (Nov - tidak boleh masuk kondisi & per_petugas)
    createMaintenanceReportOccurrence($alatSarpras, $ruang, $ac, $officers[2], $admin, [
        'scheduled_date' => '2026-10-12',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-10-12 08:30:00',
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ]);
    createMaintenanceReportOccurrence($alatMedis, $ruang, $ekg, $officers[2], $admin, [
        'scheduled_date' => '2026-11-12',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-11-12 09:00:00',
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ]);
    createMaintenanceReportOccurrence($alatSarpras, $ruang, $ac, $officers[2], $admin, [
        'scheduled_date' => '2026-11-05',
        'status' => MaintenanceStatus::Terlambat,
    ]);

    // Petugas 03..12: masing-masing 1 selesai di bulan Nov (10 selesai)
    for ($i = 3; $i <= 12; $i++) {
        createMaintenanceReportOccurrence($alatSarpras, $ruang, $ac, $officers[$i], $admin, [
            'scheduled_date' => '2026-11-14',
            'status' => MaintenanceStatus::Selesai,
            'realized_at' => '2026-11-14 09:00:00',
            'post_maintenance_condition' => AssetCondition::Good,
            'with_log' => true,
        ]);
    }

    // 4 jadwal mendatang di Des (tidak boleh masuk kondisi_pasca_pemeliharaan & per_petugas)
    for ($j = 1; $j <= 4; $j++) {
        createMaintenanceReportOccurrence($alatSarpras, $ruang, $ac, $officers[1], $admin, [
            'scheduled_date' => '2026-12-05',
            'status' => MaintenanceStatus::Terjadwal,
        ]);
    }

    // Total = 20, Selesai = 15, Presentase = 15/20 * 100 = 75%
    $response = $this->getJson(maintenanceReportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_jadwal' => 20,
            'jadwal_selesai' => 15,
            'presentase_jadwal_selesai' => 75,
        ])
        ->assertJsonPath('data.charts.per_bulan', [
            ['bulan' => 'Jan', 'jumlah' => 1],
            ['bulan' => 'Feb', 'jumlah' => 1],
            ['bulan' => 'Mar', 'jumlah' => 1],
            ['bulan' => 'Apr', 'jumlah' => 0],
            ['bulan' => 'Mei', 'jumlah' => 0],
            ['bulan' => 'Jun', 'jumlah' => 0],
            ['bulan' => 'Jul', 'jumlah' => 0],
            ['bulan' => 'Agu', 'jumlah' => 0],
            ['bulan' => 'Sep', 'jumlah' => 0],
            ['bulan' => 'Okt', 'jumlah' => 1],
            ['bulan' => 'Nov', 'jumlah' => 12],
            ['bulan' => 'Des', 'jumlah' => 4],
        ])
        ->assertJsonPath('data.charts.kondisi_pasca_pemeliharaan', [
            'Baik' => 13,
            'Rusak Ringan' => 1,
            'Rusak Berat' => 1,
        ]);

    $perPetugas = $response->json('data.charts.per_petugas');
    expect($perPetugas)->toHaveCount(12)
        ->and($perPetugas[0])->toBe(['petugas' => 'Petugas 01', 'jumlah' => 3])
        ->and($perPetugas[1])->toBe(['petugas' => 'Petugas 02', 'jumlah' => 2])
        ->and($perPetugas[11])->toBe(['petugas' => 'Petugas 12', 'jumlah' => 1]);
});

test('filter gabungan periode_awal, periode_akhir, dan klasifikasi bekerja serta menolak input tidak valid', function (): void {
    $manager = actAsMaintenanceReportRole('management');
    $officerA = User::factory()->for(Unit::factory())->create(['name' => 'Hendra Wijaya']);
    $officerB = User::factory()->for(Unit::factory())->create(['name' => 'Rina Kusuma']);

    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatMedis = AssetClassification::factory()->create(['name' => 'Alat Medis']);
    $igd = AssetLocation::factory()->create(['name' => 'IGD']);
    $icu = AssetLocation::factory()->create(['name' => 'ICU']);

    $server = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Server']);
    $monitor = AssetItem::factory()->create(['asset_classification_id' => $alatMedis->id, 'name' => 'Monitor Pasien']);

    // September (di luar rentang Okt-Nov)
    createMaintenanceReportOccurrence($alatIt, $igd, $server, $officerA, $manager, [
        'scheduled_date' => '2026-09-20',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-09-20 08:00:00',
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ]);
    // Oktober - Alat IT - Selesai
    createMaintenanceReportOccurrence($alatIt, $igd, $server, $officerA, $manager, [
        'scheduled_date' => '2026-10-12',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-10-12 09:00:00',
        'post_maintenance_condition' => AssetCondition::LightDamage,
        'with_log' => true,
    ]);
    // November - Alat IT - Terlambat
    createMaintenanceReportOccurrence($alatIt, $igd, $server, $officerA, $manager, [
        'scheduled_date' => '2026-11-10',
        'status' => MaintenanceStatus::Terlambat,
    ]);
    // November - Alat IT - Terjadwal
    createMaintenanceReportOccurrence($alatIt, $icu, $server, $officerB, $manager, [
        'scheduled_date' => '2026-11-28',
        'status' => MaintenanceStatus::Terjadwal,
    ]);
    // November - Alat Medis - Selesai
    createMaintenanceReportOccurrence($alatMedis, $igd, $monitor, $officerB, $manager, [
        'scheduled_date' => '2026-11-15',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-11-15 10:00:00',
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ]);

    // Filter menggunakan periode_awal, periode_akhir, dan klasifikasi (Alat IT)
    $this->getJson(maintenanceReportUrl([
        'periode_awal' => '2026-10-01',
        'periode_akhir' => '2026-11-30',
        'klasifikasi' => $alatIt->id,
    ]))->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_jadwal' => 3,
            'jadwal_selesai' => 1,
            'presentase_jadwal_selesai' => 33.33,
        ])
        ->assertJsonPath('data.charts.kondisi_pasca_pemeliharaan', [
            'Baik' => 0,
            'Rusak Ringan' => 1,
            'Rusak Berat' => 0,
        ])
        ->assertJsonPath('data.charts.per_petugas', [
            ['petugas' => 'Hendra Wijaya', 'jumlah' => 1],
        ]);

    // Filter menggunakan periode_awal, periode_akhir, dan klasifikasi (Alat Medis)
    $this->getJson(maintenanceReportUrl([
        'periode_awal' => '2026-11-01',
        'periode_akhir' => '2026-11-30',
        'klasifikasi' => $alatMedis->id,
    ]))->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_jadwal' => 1,
            'jadwal_selesai' => 1,
            'presentase_jadwal_selesai' => 100,
        ])
        ->assertJsonPath('data.charts.per_petugas', [
            ['petugas' => 'Rina Kusuma', 'jumlah' => 1],
        ]);

    // Filter hanya periode_akhir tanpa periode_awal tetap valid
    $this->getJson(maintenanceReportUrl([
        'periode_akhir' => '2026-09-30',
    ]))->assertOk()
        ->assertJsonPath('data.summary.jumlah_jadwal', 1);

    // Validasi filter tidak valid -> 422
    foreach ([
        '?periode_awal=2026/10/01',
        '?periode_awal=bukan-tanggal',
        '?periode_akhir=bukan-tanggal',
        '?periode_awal=2026-11-20&periode_akhir=2026-11-10',
        '?klasifikasi=999999',
        '?klasifikasi=bukan-angka',
    ] as $query) {
        $this->getJson('/api/v1/reports/maintenance'.$query)->assertUnprocessable();
    }

    // Validasi format export tidak valid -> 422
    foreach (['', '?format=csv', '?format=docx'] as $query) {
        $this->getJson('/api/v1/reports/maintenance/export'.$query)->assertUnprocessable();
    }
});

test('super-admin dan management memiliki akses penuh sedangkan koordinator-sarpras mengecualikan Alat IT di JSON, PDF, dan seluruh sheet XLSX', function (): void {
    $officerIt = User::factory()->for(Unit::factory())->create(['name' => 'Teknisi IT']);
    $officerSarpras = User::factory()->for(Unit::factory())->create(['name' => 'Teknisi Sarpras']);
    $creator = User::factory()->for(Unit::factory())->create();

    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras']);
    $alatMedis = AssetClassification::factory()->create(['name' => 'Alat Medis']);
    $ruangUmum = AssetLocation::factory()->create(['name' => 'Ruang Umum']);

    $server = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Server']);
    $ac = AssetItem::factory()->create(['asset_classification_id' => $alatSarpras->id, 'name' => 'AC']);
    $tensimeter = AssetItem::factory()->create(['asset_classification_id' => $alatMedis->id, 'name' => 'Tensimeter']);

    createMaintenanceReportOccurrence($alatIt, $ruangUmum, $server, $officerIt, $creator, [
        'scheduled_date' => '2026-11-10',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-11-10 08:00:00',
        'post_maintenance_condition' => AssetCondition::HeavyDamage,
        'with_log' => true,
    ], '0601010001');

    createMaintenanceReportOccurrence($alatSarpras, $ruangUmum, $ac, $officerSarpras, $creator, [
        'scheduled_date' => '2026-11-12',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-11-12 09:00:00',
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ], '0301010002', ['detail_item' => '2 PK']);

    createMaintenanceReportOccurrence($alatMedis, $ruangUmum, $tensimeter, $officerSarpras, $creator, [
        'scheduled_date' => '2026-11-25',
        'status' => MaintenanceStatus::Terjadwal,
    ], '0401010003');

    // super-admin dan management melihat seluruh 3 occurrence termasuk Alat IT
    foreach (['super-admin', 'management'] as $role) {
        actAsMaintenanceReportRole($role);

        $this->getJson(maintenanceReportUrl())->assertOk()
            ->assertJsonPath('data.summary', [
                'jumlah_jadwal' => 3,
                'jadwal_selesai' => 2,
                'presentase_jadwal_selesai' => 66.67,
            ])
            ->assertJsonPath('data.charts.kondisi_pasca_pemeliharaan', [
                'Baik' => 1,
                'Rusak Ringan' => 0,
                'Rusak Berat' => 1,
            ]);
    }

    // koordinator-sarpras mengecualikan Alat IT
    actAsMaintenanceReportRole('koordinator-sarpras');

    $this->getJson(maintenanceReportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_jadwal' => 2,
            'jadwal_selesai' => 1,
            'presentase_jadwal_selesai' => 50,
        ])
        ->assertJsonPath('data.charts.kondisi_pasca_pemeliharaan', [
            'Baik' => 1,
            'Rusak Ringan' => 0,
            'Rusak Berat' => 0,
        ])
        ->assertJsonPath('data.charts.per_petugas', [
            ['petugas' => 'Teknisi Sarpras', 'jumlah' => 1],
        ]);

    // Sekalipun memaksa filter klasifikasi=Alat IT, tetap menghasilkan 0
    $this->getJson(maintenanceReportUrl(['klasifikasi' => $alatIt->id]))->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_jadwal' => 0,
            'jadwal_selesai' => 0,
            'presentase_jadwal_selesai' => 0,
        ])
        ->assertJsonPath('data.charts.per_petugas', []);

    // Export XLSX untuk koordinator-sarpras: Sheet 1 Semua Data + Alat Medis + Alat Sarpras (tanpa sheet & data Alat IT)
    $xlsxResponse = $this->get(maintenanceReportExportUrl(['format' => 'xlsx']))->assertOk();
    $workbook = parseMaintenanceXlsxWorkbook($xlsxResponse->getContent());

    expect($workbook['sheet_names'])->toBe(['Semua Data', 'Alat Medis', 'Alat Sarpras'])
        ->and($workbook['sheets']['Semua Data']['rows'])->toHaveCount(2)
        ->and(array_column($workbook['sheets']['Semua Data']['rows'], 3))->not->toContain('Alat IT')
        ->toContain('Alat Sarpras', 'Alat Medis');

    // Alias role snake_case koordinator_sarpras juga mengecualikan Alat IT
    $snakeRole = Role::firstOrCreate(['name' => 'koordinator_sarpras', 'guard_name' => 'web']);
    $snakeRole->givePermissionTo(['maintenance-reports-read', 'maintenance-reports-export']);
    actAsMaintenanceReportRole('koordinator_sarpras');

    $this->getJson(maintenanceReportUrl())->assertOk()
        ->assertJsonPath('data.summary.jumlah_jadwal', 2);
});

test('export xlsx menghasilkan multi-sheet dengan baris 1 langsung header tabel, kode aset bertipe string, kondisi, petugas, dan keterangan keterlambatan', function (): void {
    $admin = actAsMaintenanceReportRole('super-admin');
    $officer = User::factory()->for(Unit::factory())->create(['name' => 'Hendra Wijaya']);

    $alatIt = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $alatSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras']);
    $ruangIt = AssetLocation::factory()->create(['name' => 'Ruang Server']);
    $server = AssetItem::factory()->create(['asset_classification_id' => $alatIt->id, 'name' => 'Server']);
    $ac = AssetItem::factory()->create(['asset_classification_id' => $alatSarpras->id, 'name' => 'AC']);

    // 1) Selesai tepat waktu (Alat IT)
    createMaintenanceReportOccurrence($alatIt, $ruangIt, $server, $officer, $admin, [
        'period' => MaintenancePeriod::Monthly,
        'scheduled_date' => '2026-11-10',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-11-10 09:15:00',
        'is_late' => false,
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ], '0602010001', ['detail_item' => 'Rack A']);

    // 2) Selesai terlambat 3 hari (Alat IT)
    createMaintenanceReportOccurrence($alatIt, $ruangIt, $server, $officer, $admin, [
        'period' => MaintenancePeriod::Quarterly,
        'scheduled_date' => '2026-11-10',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-11-13 14:00:00',
        'is_late' => true,
        'post_maintenance_condition' => AssetCondition::LightDamage,
        'with_log' => true,
    ], '0602010002', ['detail_item' => 'Rack B']);

    // 3) Overdue 4 hari (Alat Sarpras)
    createMaintenanceReportOccurrence($alatSarpras, $ruangIt, $ac, $officer, $admin, [
        'period' => MaintenancePeriod::Weekly,
        'scheduled_date' => '2026-11-14',
        'status' => MaintenanceStatus::Terlambat,
    ], '0302010003', ['detail_item' => '2 PK']);

    $response = $this->get(maintenanceReportExportUrl([
        'format' => 'xlsx',
        'periode_awal' => '2026-11-01',
        'periode_akhir' => '2026-11-30',
    ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="laporan-maintenance.xlsx"');

    $workbook = parseMaintenanceXlsxWorkbook($response->getContent());

    $expectedHeaders = [
        'No. Inventaris',
        'Kode Aset',
        'Nama Aset',
        'Klasifikasi',
        'Lokasi Ruangan',
        'Periode',
        'Tanggal Terjadwal',
        'Tanggal Realisasi',
        'Status',
        'Kondisi Pasca Pemeliharaan',
        'Petugas Pelaksana',
        'Keterangan Keterlambatan',
    ];

    expect($workbook['sheet_names'])->toBe(['Semua Data', 'Alat IT', 'Alat Sarpras']);

    foreach ($workbook['sheet_names'] as $sheetName) {
        expect($workbook['sheets'][$sheetName]['headers'])->toBe($expectedHeaders)
            ->and($workbook['sheets'][$sheetName]['auto_filter'])->toBe('A1:L1');
    }

    expect($workbook['sheets']['Semua Data']['rows'])->toBe([
        [
            '0602010001',
            '0602010001',
            'Server Rack A',
            'Alat IT',
            'Ruang Server',
            'Bulanan',
            '2026-11-10',
            '2026-11-10 16:15:00',
            'Selesai',
            'Baik',
            'Hendra Wijaya',
            'Tepat Waktu',
        ],
        [
            '0602010002',
            '0602010002',
            'Server Rack B',
            'Alat IT',
            'Ruang Server',
            '3 Bulan (Triwulan)',
            '2026-11-10',
            '2026-11-13 21:00:00',
            'Selesai',
            'Rusak Ringan',
            'Hendra Wijaya',
            'Terlambat 3 hari',
        ],
        [
            '0302010003',
            '0302010003',
            'AC 2 PK',
            'Alat Sarpras',
            'Ruang Server',
            'Mingguan',
            '2026-11-14',
            '-',
            'Terlambat',
            '-',
            'Hendra Wijaya',
            'Terlambat 4 hari',
        ],
    ]);

    expect($workbook['sheets']['Alat IT']['rows'])->toHaveCount(2)
        ->and($workbook['sheets']['Alat Sarpras']['rows'])->toHaveCount(1);

    // Pastikan No. Inventaris (kolom 0) dan Kode Aset (kolom 1) bertipe string
    foreach ($workbook['sheets']['Semua Data']['types'] as $rowTypes) {
        expect($rowTypes[0])->toBe(DataType::TYPE_STRING)
            ->and($rowTypes[1])->toBe(DataType::TYPE_STRING);
    }
});

test('export pdf menggunakan template khusus reports.maintenance.pdf dengan grafik SVG aman, metadata pengunduh, dan file PDF valid', function (): void {
    $admin = actAsMaintenanceReportRole('super-admin');
    $officer = User::factory()->for(Unit::factory())->create(['name' => 'Petugas <script>alert(1)</script> & Tim']);

    $classification = AssetClassification::factory()->create(['name' => 'Alat & Medis "Utama"']);
    $location = AssetLocation::factory()->create(['name' => 'IGD']);
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id, 'name' => 'Monitor']);

    createMaintenanceReportOccurrence($classification, $location, $item, $officer, $admin, [
        'scheduled_date' => '2026-11-10',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-11-10 09:00:00',
        'post_maintenance_condition' => AssetCondition::Good,
        'with_log' => true,
    ]);
    createMaintenanceReportOccurrence($classification, $location, $item, $officer, $admin, [
        'scheduled_date' => '2026-11-12',
        'status' => MaintenanceStatus::Terlambat,
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

    $response = $this->get(maintenanceReportExportUrl([
        'format' => 'pdf',
        'periode_awal' => '2026-11-01',
        'periode_akhir' => '2026-11-30',
        'klasifikasi' => $classification->id,
    ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="laporan-maintenance.pdf"');

    $pdf = $response->getContent();
    expect($pdf)->toMatch('/^%PDF-\d\.\d/')
        ->toContain('startxref', '%%EOF');

    expect($capturedView)->toBe('reports.maintenance.pdf')
        ->and($capturedPage['footer_html'] ?? '')->toContain('Halaman', 'pageNumber', 'totalPages')
        ->and($capturedPayload)->toBeArray()
        ->toHaveKeys(['title', 'subtitle', 'downloaded_at', 'filters', 'summary', 'charts'])
        ->and($capturedPayload['title'])->toBe('Laporan Maintenance')
        ->and($capturedPayload['subtitle'])->toBe('RS Citra Husada Jember')
        ->and($capturedPayload['downloaded_at'])->toContain('18 Nov 2026', $admin->name, 'Super Admin')
        ->and($capturedPayload['filters']['Periode'])->toBe('01 Nov 2026 - 30 Nov 2026')
        ->and($capturedPayload['summary'])->toBe([
            'jumlah_jadwal' => 2,
            'jadwal_selesai' => 1,
            'presentase_jadwal_selesai' => 50.0,
            'presentase_jadwal_selesai_formatted' => '50%',
        ])
        ->and($capturedPayload['charts']['per_bulan_svg'])->toContain('<svg', '</svg>', 'Jan', 'Des')
        ->not->toContain('<script>')
        ->and($capturedPayload['charts']['kondisi_donut_svg'])->toContain('<svg', '</svg>', 'Alat', '#10B981')
        ->not->toContain('<script>');
});

test('laporan dan export maintenance pada kondisi data kosong mengembalikan 12 bulan nol, kondisi nol, dan file valid', function (): void {
    actAsMaintenanceReportRole('management');

    $this->getJson(maintenanceReportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_jadwal' => 0,
            'jadwal_selesai' => 0,
            'presentase_jadwal_selesai' => 0,
        ])
        ->assertJsonPath('data.charts', [
            'per_bulan' => [
                ['bulan' => 'Jan', 'jumlah' => 0],
                ['bulan' => 'Feb', 'jumlah' => 0],
                ['bulan' => 'Mar', 'jumlah' => 0],
                ['bulan' => 'Apr', 'jumlah' => 0],
                ['bulan' => 'Mei', 'jumlah' => 0],
                ['bulan' => 'Jun', 'jumlah' => 0],
                ['bulan' => 'Jul', 'jumlah' => 0],
                ['bulan' => 'Agu', 'jumlah' => 0],
                ['bulan' => 'Sep', 'jumlah' => 0],
                ['bulan' => 'Okt', 'jumlah' => 0],
                ['bulan' => 'Nov', 'jumlah' => 0],
                ['bulan' => 'Des', 'jumlah' => 0],
            ],
            'kondisi_pasca_pemeliharaan' => [
                'Baik' => 0,
                'Rusak Ringan' => 0,
                'Rusak Berat' => 0,
            ],
            'per_petugas' => [],
        ]);

    $xlsxResponse = $this->get(maintenanceReportExportUrl(['format' => 'xlsx']))->assertOk();
    $workbook = parseMaintenanceXlsxWorkbook($xlsxResponse->getContent());
    expect($workbook['sheet_names'])->toBe(['Semua Data'])
        ->and($workbook['sheets']['Semua Data']['headers'])->toBe([
            'No. Inventaris',
            'Kode Aset',
            'Nama Aset',
            'Klasifikasi',
            'Lokasi Ruangan',
            'Periode',
            'Tanggal Terjadwal',
            'Tanggal Realisasi',
            'Status',
            'Kondisi Pasca Pemeliharaan',
            'Petugas Pelaksana',
            'Keterangan Keterlambatan',
        ])->and($workbook['sheets']['Semua Data']['rows'])->toBe([]);

    $pdfResponse = $this->get(maintenanceReportExportUrl(['format' => 'pdf']))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($pdfResponse->getContent())->toMatch('/^%PDF-\d\.\d/');
});

test('guest dan role tanpa izin tidak dapat mengakses laporan maupun export maintenance', function (): void {
    expect(route('api.v1.reports.maintenance.index', [], false))->toBe('/api/v1/reports/maintenance')
        ->and(route('api.v1.reports.maintenance.export', [], false))->toBe('/api/v1/reports/maintenance/export');

    $this->getJson(maintenanceReportUrl())->assertUnauthorized();
    $this->getJson(maintenanceReportExportUrl(['format' => 'xlsx']))->assertUnauthorized();

    foreach (['user', 'petugas-tik', 'petugas-sarpras'] as $role) {
        $user = actAsMaintenanceReportRole($role);
        $this->getJson(maintenanceReportUrl())->assertForbidden();
        $this->getJson(maintenanceReportExportUrl(['format' => 'xlsx']))->assertForbidden();

        if ($role === 'user') {
            $user->givePermissionTo(['maintenance-reports-read', 'maintenance-reports-export']);
            $this->getJson(maintenanceReportUrl())->assertForbidden();
            $this->getJson(maintenanceReportExportUrl(['format' => 'pdf']))->assertForbidden();
        }
    }

    // Role yang diizinkan tanpa permission export tidak dapat mengakses endpoint export
    $roleWithoutExport = Role::firstOrCreate(['name' => 'koordinator_sarpras', 'guard_name' => 'web']);
    $roleWithoutExport->syncPermissions(['maintenance-reports-read']);
    actAsMaintenanceReportRole('koordinator_sarpras');

    $this->getJson(maintenanceReportUrl())->assertOk();
    $this->getJson(maintenanceReportExportUrl(['format' => 'xlsx']))->assertForbidden();
});
