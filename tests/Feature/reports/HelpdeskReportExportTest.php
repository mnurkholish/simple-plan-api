<?php

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\ItTag;
use App\Models\QualityCategory;
use App\Models\SarprasCategory;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([DomainPermissionSeeder::class, RoleSeeder::class]);
    $this->reporter = User::factory()->for(Unit::factory())->create();
});

function helpdeskExportUrl(array $filters = []): string
{
    return '/api/v1/reports/helpdesk/export?'.http_build_query(array_merge([
        'format' => 'xlsx',
        'periode_awal' => '2026-01-01',
        'periode_akhir' => '2026-03-31',
    ], $filters));
}

function actAsHelpdeskExportRole(string $role): User
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole(Role::findByName($role, 'web'));
    Sanctum::actingAs($user);

    return $user;
}

/** @param array<string, mixed> $attributes */
function createHelpdeskExportTicket(User $reporter, TicketService $service, TicketStatus $status, string $createdAt, array $attributes = []): Ticket
{
    $ticket = Ticket::factory()->reportedBy($reporter)->create([
        'service' => $service,
        'status' => $status,
        ...$attributes,
    ]);
    $ticket->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

    return $ticket;
}

function readHelpdeskExportWorkbook(string $content): Spreadsheet
{
    $stream = tmpfile();
    if ($stream === false) {
        throw new RuntimeException('Cannot open temporary workbook stream.');
    }

    try {
        fwrite($stream, $content);

        return IOFactory::load(stream_get_meta_data($stream)['uri']);
    } finally {
        fclose($stream);
    }
}

test('export Excel berisi enam sheet, rincian tiket, rekap, dan grafik sesuai filter', function (): void {
    actAsHelpdeskExportRole('super-admin');
    $quality = QualityCategory::create(['name' => 'Kualitas aplikasi']);
    $tag = ItTag::create(['name' => 'Server']);
    $category = SarprasCategory::create(['name' => 'Elektronik']);
    $asset = Asset::factory()->create(['detail_item' => 'Laptop Klinik']);
    $asset->assetCode->forceFill(['full_code' => '000123'])->save();

    $tik = createHelpdeskExportTicket($this->reporter, TicketService::Tik, TicketStatus::Terselesaikan, '2026-01-15 08:00:00', [
        'asset_id' => $asset->id,
        'completed_at' => '2026-01-15 10:00:00',
        'sla_deadline' => '2026-01-15 10:00:00',
    ]);
    $tik->tikDetail()->create(['quality_category_id' => $quality->id, 'it_tag_id' => $tag->id]);
    $sarpras = createHelpdeskExportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Ditutup, '2026-03-31 23:59:59', [
        'completed_at' => '2026-04-01 03:59:59',
        'sla_deadline' => '2026-04-01 03:00:00',
    ]);
    $sarpras->sarprasDetail()->create(['sarpras_category_id' => $category->id]);
    createHelpdeskExportTicket($this->reporter, TicketService::Tik, TicketStatus::Ditolak, '2026-02-10 09:00:00');
    createHelpdeskExportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Baru, '2026-04-01 00:00:00');

    $response = $this->get(helpdeskExportUrl())->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="laporan-helpdesk-2026-01-01-2026-03-31.xlsx"');
    $book = readHelpdeskExportWorkbook($response->getContent());

    try {
        expect($book->getSheetNames())->toBe(['Rincian Tiket', 'Rekap', 'Tiket per Bulan', 'Status Tiket', 'Kategori Tiket', 'SLA']);
        $details = $book->getSheetByName('Rincian Tiket');
        expect($details->getHighestDataRow())->toBe(4);
        $rows = $details->toArray();
        $tikRow = collect($rows)->first(fn (array $row): bool => $row[0] === $tik->ticket_number);
        $sarprasRow = collect($rows)->first(fn (array $row): bool => $row[0] === $sarpras->ticket_number);
        expect($tikRow[3])->toBe('000123');
        expect($tikRow[4])->toBe('Laptop Klinik');
        expect($tikRow[5])->toBe('Server');
        expect($tikRow[11])->toBe('Tercapai');
        expect($sarprasRow[5])->toBe('Elektronik');
        expect($sarprasRow[11])->toBe('Terlambat');
        expect($details->getCell('D'.(array_search($tikRow, $rows, true) + 1))->getDataType())->toBe('s');
        expect(collect($rows)->pluck(11)->toArray())->toContain('Tidak berlaku');

        expect($book->getSheetByName('Rekap')->getCell('B2')->getValue())->toBe(3);
        expect($book->getSheetByName('Rekap')->getCell('B3')->getValue())->toBe(2);
        expect($book->getSheetByName('Rekap')->getCell('B4')->getValue())->toBe(1);
        expect($book->getSheetByName('Rekap')->getCell('B5')->getValue())->toEqual(3);
        expect($book->getSheetByName('Rekap')->getCell('B6')->getValue())->toEqual(50);
        expect($book->getSheetByName('Tiket per Bulan')->getCell('B3')->getValue())->toBe(1);
        $categoryRows = collect($book->getSheetByName('Kategori Tiket')->toArray())
            ->map(fn (array $row): string => implode(':', $row))->all();
        expect($categoryRows)->toContain('Sarpras:Elektronik:1', 'TIK:Server:1');
        expect($book->getSheetByName('SLA')->getCell('B2')->getValue())->toBe(1);
        expect($book->getSheetByName('SLA')->getCell('B3')->getValue())->toBe(1);
    } finally {
        $book->disconnectWorksheets();
    }
});

test('export menolak format dan filter yang tidak valid', function (): void {
    actAsHelpdeskExportRole('super-admin');

    foreach (['', '?format=csv&periode_awal=2026-01-01&periode_akhir=2026-03-31', '?format=xlsx&periode_awal=2026-01-01', '?format=xlsx&periode_awal=2026-03-31&periode_akhir=2026-01-01', '?format=xlsx&periode_awal=2026-01-01&periode_akhir=2026-03-31&jenis_layanan=lain'] as $query) {
        $this->getJson('/api/v1/reports/helpdesk/export'.$query)->assertUnprocessable();
    }
});

test('super admin dan manajemen dapat export semua layanan, koordinator hanya Sarpras', function (): void {
    createHelpdeskExportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-02-10 09:00:00');
    createHelpdeskExportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Baru, '2026-02-10 09:00:00');

    foreach (['super-admin', 'management'] as $role) {
        actAsHelpdeskExportRole($role);
        $book = readHelpdeskExportWorkbook($this->get(helpdeskExportUrl())->assertOk()->getContent());
        expect($book->getSheetByName('Rincian Tiket')->getHighestDataRow())->toBe(3);
        $book->disconnectWorksheets();
    }

    actAsHelpdeskExportRole('koordinator-sarpras');
    $book = readHelpdeskExportWorkbook($this->get(helpdeskExportUrl())->assertOk()->getContent());
    expect($book->getSheetByName('Rincian Tiket')->getHighestDataRow())->toBe(2);
    expect($book->getSheetByName('Rincian Tiket')->getCell('B2')->getValue())->toBe('Sarpras');
    $book->disconnectWorksheets();

    $book = readHelpdeskExportWorkbook($this->get(helpdeskExportUrl(['jenis_layanan' => 'tik']))->assertOk()->getContent());
    expect($book->getSheetByName('Rincian Tiket')->getHighestDataRow())->toBe(1);
    expect($book->getSheetByName('Rekap')->getCell('B2')->getValue())->toBe(0);
    $book->disconnectWorksheets();
});

test('guest dan role tanpa izin tidak dapat export laporan', function (): void {
    $this->getJson(helpdeskExportUrl())->assertUnauthorized();

    foreach (['user', 'petugas-tik', 'petugas-sarpras'] as $role) {
        $user = actAsHelpdeskExportRole($role);
        $this->getJson(helpdeskExportUrl())->assertForbidden();
        if ($role === 'user') {
            $user->givePermissionTo('helpdesk-reports-export');
            $this->getJson(helpdeskExportUrl())->assertForbidden();
        }
    }
});
