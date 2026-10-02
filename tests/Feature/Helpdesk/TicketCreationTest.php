<?php

use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function actingAsTicketCreator(?User $user = null): User
{
    $user ??= User::factory()->create(['unit_id' => Unit::factory()]);
    $permission = Permission::firstOrCreate([
        'name' => 'tickets-create',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
    Sanctum::actingAs($user);

    return $user;
}

test('guest tidak bisa membuat tiket', function (): void {
    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
    ])->assertUnauthorized();
});

test('User tanpa permission tickets-create tidak dapat membuat tiket', function (): void {
    Sanctum::actingAs(User::factory()->create(['unit_id' => Unit::factory()]));

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
    ])->assertForbidden();

    expect(Ticket::query()->count())->toBe(0);
});

test('setiap role yang diizinkan dapat membuat tiket', function (string $role): void {
    $this->seed([
        CorePermissionSeeder::class,
        DomainPermissionSeeder::class,
        RoleSeeder::class,
    ]);

    $reporter = User::factory()->create(['unit_id' => Unit::factory()]);
    $reporter->assignRole($role);
    Sanctum::actingAs($reporter);

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => "Tiket oleh {$role}.",
    ])->assertCreated();
})->with([
    'super admin' => 'super-admin',
    'koordinator sarpras' => 'koordinator-sarpras',
    'petugas TIK' => 'petugas-tik',
    'petugas Sarpras' => 'petugas-sarpras',
    'user' => 'user',
    'management' => 'management',
]);

test('membuat tiket TIK dengan data minimum yang valid berhasil', function (): void {
    $unit = Unit::factory()->create();
    $reporter = User::factory()->create(['unit_id' => $unit->id]);
    actingAsTicketCreator($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Tiket berhasil dibuat.')
        ->assertJsonPath('data.service', 'tik')
        ->assertJsonPath('data.description', 'Komputer tidak dapat menyala.')
        ->assertJsonPath('data.status', TicketStatus::Baru->value)
        ->assertJsonPath('data.reporter.id', $reporter->id)
        ->assertJsonPath('data.unit.id', $unit->id);

    expect($response->json('data.ticket_number'))->toMatch('/^TIK-\d{4}-\d{6}$/');

    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'service' => 'tik',
        'reporter_id' => $reporter->id,
        'unit_id' => $unit->id,
        'description' => 'Komputer tidak dapat menyala.',
        'status' => TicketStatus::Baru->value,
    ]);
});

test('membuat tiket sarpras dengan data minimum yang valid berhasil', function (): void {
    $unit = Unit::factory()->create();
    $reporter = User::factory()->create(['unit_id' => $unit->id]);
    actingAsTicketCreator($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'sarpras',
        'description' => 'Lampu ruang pemeriksaan mati.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Tiket berhasil dibuat.')
        ->assertJsonPath('data.service', 'sarpras')
        ->assertJsonPath('data.description', 'Lampu ruang pemeriksaan mati.')
        ->assertJsonPath('data.status', TicketStatus::Baru->value)
        ->assertJsonPath('data.reporter.id', $reporter->id)
        ->assertJsonPath('data.unit.id', $unit->id);

    expect($response->json('data.ticket_number'))->toMatch('/^SPR-\d{4}-\d{6}$/');

    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'service' => 'sarpras',
        'reporter_id' => $reporter->id,
        'unit_id' => $unit->id,
        'description' => 'Lampu ruang pemeriksaan mati.',
        'status' => TicketStatus::Baru->value,
    ]);
});

test('membuat tiket hanya menerima service yang TIK atau Sarpras', function (): void {
    actingAsTicketCreator();

    $this->postJson('/api/v1/tickets', [
        'service' => 'umum',
        'description' => 'Kerusakan perlu ditangani.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service');
});

test('pelapor harus memiliki unit untuk membuat tiket', function (): void {
    $reporter = User::factory()->create(['unit_id' => null]);
    actingAsTicketCreator($reporter);

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_id');

    expect(Ticket::query()->count())->toBe(0);
});

test('asset opsional dan asset yang valid dapat disambungkan ke tiket', function (): void {
    $unit = Unit::factory()->create();
    $reporter = User::factory()->create(['unit_id' => $unit->id]);
    $asset = Asset::create([
        'asset_number' => 'AST-0001',
        'name' => 'Komputer Poli',
        'brand' => 'Dell',
        'unit_id' => $unit->id,
        'location' => 'Poli Umum',
        'status' => 'active',
    ]);
    actingAsTicketCreator($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'asset_id' => $asset->id,
        'description' => 'Komputer tidak menyala.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.asset.id', $asset->id)
        ->assertJsonPath('data.asset.asset_number', 'AST-0001')
        ->assertJsonPath('data.asset.name', 'Komputer Poli');
    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'asset_id' => $asset->id,
    ]);
});

test('asset yang tidak ditemukan akan ditolak', function (int $assetId): void {
    actingAsTicketCreator();

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'asset_id' => $assetId,
        'description' => 'Komputer tidak menyala.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('asset_id');
})->with([
    'asset tidak ditemukan' => 999999,
    'asset dihapus' => function (): int {
        $unit = Unit::factory()->create();
        $asset = Asset::create([
            'asset_number' => 'AST-DELETED',
            'name' => 'Aset Dihapus',
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);
        $asset->delete();

        return $asset->id;
    },
]);

test('initial evidence yang valid dapat disimpan beserta portable metadata yang disertakan dalam parameter.', function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    actingAsTicketCreator();

    $response = $this->post('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Monitor menampilkan garis.',
        'initial_evidence' => UploadedFile::fake()->image('monitor-rusak.jpg')->size(512),
    ], ['Accept' => 'application/json']);

    $response
        ->assertCreated()
        ->assertJsonPath('data.initial_evidence.original_name', 'monitor-rusak.jpg')
        ->assertJsonPath('data.initial_evidence.mime_type', 'image/jpeg');

    $objectKey = $response->json('data.initial_evidence.object_key');

    expect($objectKey)->toStartWith('helpdesk/evidence/');
    Storage::disk('local')->assertExists($objectKey);

    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'initial_evidence_object_key' => $objectKey,
        'initial_evidence_original_name' => 'monitor-rusak.jpg',
        'initial_evidence_mime_type' => 'image/jpeg',
    ]);
});

test('initial evidence harus sebuah gambar dan tidak lebih besar dari 2 mb', function (UploadedFile $file): void {
    Storage::fake('local');
    actingAsTicketCreator();

    $this->post('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Monitor menampilkan garis.',
        'initial_evidence' => $file,
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('initial_evidence');
})->with([
    'bukan berupa gambar' => fn (): UploadedFile => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
    'gambar melebihi limit size' => fn (): UploadedFile => UploadedFile::fake()->image('large.jpg')->size(2049),
]);
