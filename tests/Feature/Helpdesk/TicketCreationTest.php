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
use Illuminate\Support\Carbon;
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

test('guest cannot create a ticket', function (): void {
    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
    ])->assertUnauthorized();
});

test('authenticated user without tickets-create permission cannot create a ticket', function (): void {
    Sanctum::actingAs(User::factory()->create(['unit_id' => Unit::factory()]));

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
    ])->assertForbidden();

    expect(Ticket::query()->count())->toBe(0);
});

test('every final role can create a ticket', function (string $role): void {
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
    'sarpras coordinator' => 'koordinator-sarpras',
    'TIK officer' => 'petugas-tik',
    'Sarpras officer' => 'petugas-sarpras',
    'user' => 'user',
    'management' => 'management',
]);

test('authenticated user can create a TIK ticket without classification data', function (): void {
    $unit = Unit::factory()->create();
    $reporter = User::factory()->create(['unit_id' => $unit->id]);
    actingAsTicketCreator($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
        'quality_category_id' => 999,
        'it_tag_id' => 999,
        'custom_it_tag_text' => 'Tidak boleh dipakai ketika create.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Tiket berhasil dibuat.')
        ->assertJsonPath('data.service', 'tik')
        ->assertJsonPath('data.category', null)
        ->assertJsonPath('data.tik_detail', null)
        ->assertJsonPath('data.sarpras_detail', null)
        ->assertJsonPath('data.description', 'Komputer tidak dapat menyala.')
        ->assertJsonPath('data.status', 'baru')
        ->assertJsonPath('data.reporter.id', $reporter->id)
        ->assertJsonPath('data.unit.id', $unit->id)
        ->assertJsonPath('data.asset', null)
        ->assertJsonPath('data.initial_evidence', null);

    expect($response->json('data.ticket_number'))->toMatch('/^TIK-\d{4}-\d{6}$/');

    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'reporter_id' => $reporter->id,
        'unit_id' => $unit->id,
        'asset_id' => null,
        'service' => 'tik',
        'status' => TicketStatus::Baru->value,
    ]);
    $this->assertDatabaseMissing('ticket_tik_details', [
        'ticket_id' => $response->json('data.id'),
    ]);
    expect(Ticket::findOrFail($response->json('data.id'))->statusHistories()->count())->toBe(0);
});

test('authenticated user can create a Sarpras ticket with the final number prefix', function (): void {
    $reporter = User::factory()->create(['unit_id' => Unit::factory()]);
    actingAsTicketCreator($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'sarpras',
        'description' => 'Lampu ruang pemeriksaan mati.',
        'sarpras_category_id' => 999,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.service', 'sarpras')
        ->assertJsonPath('data.category', null)
        ->assertJsonPath('data.sarpras_detail', null)
        ->assertJsonPath('data.status', 'baru');

    expect($response->json('data.ticket_number'))->toMatch('/^SPR-\d{4}-\d{6}$/');
    $this->assertDatabaseMissing('ticket_sarpras_details', [
        'ticket_id' => $response->json('data.id'),
    ]);
});

test('ticket creation only requires service and description from the client', function (): void {
    actingAsTicketCreator();

    $this->postJson('/api/v1/tickets')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service', 'description'])
        ->assertJsonMissingValidationErrors(['unit_id', 'asset_id', 'initial_evidence']);
});

test('ticket creation rejects an unknown service', function (): void {
    actingAsTicketCreator();

    $this->postJson('/api/v1/tickets', [
        'service' => 'umum',
        'description' => 'Kerusakan perlu ditangani.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service');
});

test('ticket creation requires the authenticated reporter to have a unit', function (): void {
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

test('ticket creation keeps every server-controlled field authoritative', function (): void {
    $reporterUnit = Unit::factory()->create();
    $otherUnit = Unit::factory()->create();
    $reporter = User::factory()->create(['unit_id' => $reporterUnit->id]);
    $otherUser = User::factory()->create();
    actingAsTicketCreator($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'Komputer tidak dapat menyala.',
        'unit_id' => $otherUnit->id,
        'reporter_id' => $otherUser->id,
        'status' => TicketStatus::Ditutup->value,
        'priority' => 'critical',
        'classified_by_id' => $otherUser->id,
        'assigned_officer_id' => $otherUser->id,
        'classified_at' => now(),
        'assigned_at' => now(),
        'sla_started_at' => now(),
        'sla_deadline' => now(),
        'completed_at' => now(),
        'closed_at' => now(),
        'ticket_number' => 'CLIENT-CONTROLLED',
    ])->assertCreated();

    $ticket = Ticket::findOrFail($response->json('data.id'));

    expect($ticket->reporter_id)->toBe($reporter->id)
        ->and($ticket->unit_id)->toBe($reporterUnit->id)
        ->and($ticket->status)->toBe(TicketStatus::Baru)
        ->and($ticket->priority)->toBeNull()
        ->and($ticket->classified_by_id)->toBeNull()
        ->and($ticket->assigned_officer_id)->toBeNull()
        ->and($ticket->classified_at)->toBeNull()
        ->and($ticket->assigned_at)->toBeNull()
        ->and($ticket->sla_started_at)->toBeNull()
        ->and($ticket->sla_deadline)->toBeNull()
        ->and($ticket->completed_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->ticket_number)->not->toBe('CLIENT-CONTROLLED');
});

test('ticket number sequences are separate by service', function (): void {
    actingAsTicketCreator();

    $tikOne = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'TIK pertama.',
    ])->assertCreated()->json('data.ticket_number');
    $tikTwo = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'description' => 'TIK kedua.',
    ])->assertCreated()->json('data.ticket_number');
    $sarprasOne = $this->postJson('/api/v1/tickets', [
        'service' => 'sarpras',
        'description' => 'Sarpras pertama.',
    ])->assertCreated()->json('data.ticket_number');

    $year = now()->format('Y');

    expect($tikOne)->toBe("TIK-{$year}-000001")
        ->and($tikTwo)->toBe("TIK-{$year}-000002")
        ->and($sarprasOne)->toBe("SPR-{$year}-000001");
});

test('ticket number sequence resets each year', function (): void {
    actingAsTicketCreator();

    try {
        Carbon::setTestNow('2026-12-31 23:59:00');
        $firstYear = $this->postJson('/api/v1/tickets', [
            'service' => 'tik',
            'description' => 'Tiket tahun pertama.',
        ])->assertCreated()->json('data.ticket_number');

        Carbon::setTestNow('2027-01-01 00:01:00');
        $nextYear = $this->postJson('/api/v1/tickets', [
            'service' => 'tik',
            'description' => 'Tiket tahun berikutnya.',
        ])->assertCreated()->json('data.ticket_number');
    } finally {
        Carbon::setTestNow();
    }

    expect($firstYear)->toBe('TIK-2026-000001')
        ->and($nextYear)->toBe('TIK-2027-000001');
});

test('asset is optional and a valid selected asset is linked to the ticket', function (): void {
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

test('ticket creation rejects an unavailable or deleted asset', function (int $assetId): void {
    actingAsTicketCreator();

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'asset_id' => $assetId,
        'description' => 'Komputer tidak menyala.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('asset_id');
})->with([
    'unknown asset' => 999999,
    'deleted asset' => function (): int {
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

test('initial evidence is optional and stores portable metadata when supplied', function (): void {
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
        ->assertJsonPath('data.initial_evidence.mime_type', 'image/jpeg')
        ->assertJsonMissingPath('data.initial_evidence.object_key');

    $ticket = Ticket::query()->findOrFail($response->json('data.id'));
    $objectKey = $ticket->initial_evidence_object_key;

    expect($objectKey)->toStartWith('helpdesk/evidence/')
        ->and($response->json('data.initial_evidence.url'))
        ->toBeString()
        ->toContain($objectKey, 'expiration=');
    Storage::disk('local')->assertExists($objectKey);

    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'initial_evidence_object_key' => $objectKey,
        'initial_evidence_original_name' => 'monitor-rusak.jpg',
        'initial_evidence_mime_type' => 'image/jpeg',
    ]);
});

test('initial evidence must be an image no larger than two megabytes', function (UploadedFile $file): void {
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
    'non image file' => fn (): UploadedFile => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
    'image above size limit' => fn (): UploadedFile => UploadedFile::fake()->image('large.jpg')->size(2049),
]);
