<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CorePermissionSeeder::class, DomainPermissionSeeder::class, RoleSeeder::class]);
    $this->requester = User::factory()->for(Unit::factory())->create();
});

function designGraphicExportUrl(array $filters = []): string
{
    return '/api/v1/reports/design-graphic/export?'.http_build_query(array_merge([
        'format' => 'xlsx',
        'periode_awal' => '2026-01-01',
        'periode_akhir' => '2026-03-31',
    ], $filters));
}

function actAsDesignGraphicExportRole(string $role): User
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole(Role::findByName($role, 'web'));
    Sanctum::actingAs($user);

    return $user;
}

/** @param array<string, mixed> $attributes */
function createDesignGraphicExportRequest(User $requester, int $sequence, string $createdAt, array $attributes = []): DesignRequest
{
    $designRequest = DesignRequest::create([
        'request_number' => sprintf('DGR-2026-%06d', $sequence),
        'title' => "Request desain {$sequence}",
        'design_type' => 'Poster Edukasi',
        'description' => 'Desain untuk unit.',
        'needed_at' => '2026-04-01',
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Baru,
        ...$attributes,
    ]);
    $designRequest->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

    return $designRequest;
}

function readDesignGraphicExportWorkbook(string $content): Spreadsheet
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

test('export Excel berisi lima sheet, rincian request, rekap, dan grafik sesuai periode', function (): void {
    $officer = actAsDesignGraphicExportRole('super-admin');
    $finished = createDesignGraphicExportRequest($this->requester, 1, '2026-01-15 08:00:00', [
        'status' => DesignRequestStatus::Selesai,
        'design_type' => 'Poster  Edukasi',
        'assigned_officer_id' => $officer->id,
        'completed_at' => '2026-01-16 20:00:00',
    ]);
    createDesignGraphicExportRequest($this->requester, 2, '2026-03-31 23:59:59', [
        'status' => DesignRequestStatus::Ditolak,
        'design_type' => 'poster edukasi',
    ]);
    createDesignGraphicExportRequest($this->requester, 3, '2026-04-01 00:00:00', [
        'design_type' => 'Banner',
    ]);

    $response = $this->get(designGraphicExportUrl())->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="laporan-desain-grafis-2026-01-01-2026-03-31.xlsx"');
    $book = readDesignGraphicExportWorkbook($response->getContent());

    try {
        expect($book->getSheetNames())->toBe(['Rincian Request', 'Rekap', 'Request per Bulan', 'Status Request', 'Jenis Desain']);
        $details = $book->getSheetByName('Rincian Request');
        expect($details->getHighestDataRow())->toBe(3);
        expect($details->getCell('A2')->getValue())->toBe('DGR-2026-000002');
        expect($details->getCell('F2')->getValue())->toBe('-');
        expect($details->getCell('H2')->getValue())->toBe('-');
        expect($details->getCell('A3')->getValue())->toBe($finished->request_number);
        expect($details->getCell('A3')->getDataType())->toBe('s');
        expect($details->getCell('C3')->getValue())->toBe($this->requester->name);
        expect($details->getCell('D3')->getValue())->toBe($this->requester->unit->unit_name);
        expect($details->getCell('E3')->getValue())->toBe('Poster  Edukasi');
        expect($details->getCell('F3')->getValue())->toBe($officer->name);
        expect($details->getCell('H3')->getValue())->toBe('2026-01-16 20:00:00');

        expect($book->getSheetByName('Rekap')->getCell('B2')->getValue())->toBe(2);
        expect($book->getSheetByName('Rekap')->getCell('B3')->getValue())->toBe(1);
        expect($book->getSheetByName('Rekap')->getCell('B4')->getValue())->toBe(1);
        expect($book->getSheetByName('Rekap')->getCell('B5')->getValue())->toEqual(1.5);
        expect($book->getSheetByName('Request per Bulan')->getCell('B3')->getValue())->toBe(0);
        expect($book->getSheetByName('Status Request')->getCell('B6')->getValue())->toBe(1);
        expect($book->getSheetByName('Jenis Desain')->getCell('A2')->getValue())->toBe('Poster Edukasi');
        expect($book->getSheetByName('Jenis Desain')->getCell('B2')->getValue())->toBe(2);
    } finally {
        $book->disconnectWorksheets();
    }
});

test('export PDF menghasilkan berkas A4 dan tetap valid saat laporan kosong', function (): void {
    actAsDesignGraphicExportRole('management');

    $response = $this->get(designGraphicExportUrl(['format' => 'pdf']))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="laporan-desain-grafis-2026-01-01-2026-03-31.pdf"');

    $content = $response->getContent();
    expect(str_starts_with($content, '%PDF'))->toBeTrue();
    expect(preg_match_all('/\/Type\s*\/Page\b/', $content))->toBe(1);
    expect(preg_match('/\/MediaBox\s*\[0 0 ([\d.]+) ([\d.]+)\]/', $content, $mediaBox))->toBe(1);
    expect(abs((float) $mediaBox[1] - 595.28))->toBeLessThan(1);
    expect(abs((float) $mediaBox[2] - 841.89))->toBeLessThan(1);
});

test('export menolak format dan periode yang tidak valid', function (): void {
    actAsDesignGraphicExportRole('super-admin');

    foreach (['', '?format=csv&periode_awal=2026-01-01&periode_akhir=2026-03-31', '?format=xlsx&periode_awal=2026-01-01', '?format=xlsx&periode_awal=2026-03-31&periode_akhir=2026-01-01', '?format=xlsx&periode_awal=tidak-valid&periode_akhir=2026-03-31'] as $query) {
        $this->getJson('/api/v1/reports/design-graphic/export'.$query)->assertUnprocessable();
    }
});

test('super admin dan manajemen dapat export seluruh request desain', function (): void {
    createDesignGraphicExportRequest($this->requester, 11, '2026-02-01 09:00:00');
    createDesignGraphicExportRequest($this->requester, 12, '2026-02-02 09:00:00');

    foreach (['super-admin', 'management'] as $role) {
        actAsDesignGraphicExportRole($role);
        $book = readDesignGraphicExportWorkbook($this->get(designGraphicExportUrl())->assertOk()->getContent());
        expect($book->getSheetByName('Rincian Request')->getHighestDataRow())->toBe(3);
        $book->disconnectWorksheets();
    }
});

test('guest dan role lain tidak dapat export laporan desain grafis', function (): void {
    $this->getJson(designGraphicExportUrl())->assertUnauthorized();

    foreach (['koordinator-sarpras', 'petugas-tik', 'user'] as $role) {
        $user = actAsDesignGraphicExportRole($role);
        $this->getJson(designGraphicExportUrl())->assertForbidden();
        if ($role === 'user') {
            $user->givePermissionTo('design-reports-export');
            $this->getJson(designGraphicExportUrl())->assertForbidden();
        }
    }
});
