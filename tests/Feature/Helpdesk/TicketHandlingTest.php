<?php

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHandling;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $reporter = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $reporter->assignRole($role);
});

/**
 * @param  list<string>  $additionalPermissions
 */
function actingAsTicketHandler(
    ?Ticket $ticket = null,
    ?User $user = null,
    array $additionalPermissions = [],
): User {
    $user ??= User::factory()->create();
    $roleName = $ticket?->service === TicketService::Sarpras
        ? 'petugas-sarpras'
        : 'petugas-tik';
    $role = Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $user->assignRole($role);
    $ticket?->update(['assigned_officer_id' => $user->getKey()]);

    collect(['tickets-handle', ...$additionalPermissions])
        ->each(function (string $permissionName) use ($user): void {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            $user->givePermissionTo($permission);
        });

    Sanctum::actingAs($user);

    return $user;
}

test('assigned handler can escalate and resume a processed ticket', function (): void {
    Notification::fake();
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    $handler = actingAsTicketHandler($ticket);

    $this->postJson("/api/v1/tickets/{$ticket->id}/escalate", [
        'target' => 'Vendor',
        'notes' => 'Membutuhkan pemeriksaan dari vendor.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Eskalasi->value);

    $this->assertDatabaseHas('ticket_escalations', [
        'ticket_id' => $ticket->id,
        'escalated_by_id' => $handler->id,
        'target' => 'Vendor',
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/de-escalate")
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Diproses->value);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Diproses);
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Eskalasi->value,
        'to_status' => TicketStatus::Diproses->value,
        'changed_by_id' => $handler->id,
    ]);
});

test('a processed ticket records handling activity without redundant status history', function (): void {
    $handler = User::factory()->create(['name' => 'Petugas Penanganan']);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket, $handler);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Koneksi kabel daya diperiksa dan dikencangkan.',
        'status' => TicketStatus::Diproses->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Riwayat penanganan berhasil disimpan.')
        ->assertJsonPath('data.status', 'diproses')
        ->assertJsonPath('data.completed_at', null)
        ->assertJsonPath('data.handlings.0.notes', 'Koneksi kabel daya diperiksa dan dikencangkan.')
        ->assertJsonPath('data.handlings.0.status', 'diproses')
        ->assertJsonPath('data.handlings.0.handled_by.id', $handler->id)
        ->assertJsonPath('data.handlings.0.handled_by.name', 'Petugas Penanganan')
        ->assertJsonMissingPath('data.handlings.0.handled_by.email');

    $this->assertDatabaseHas('ticket_handlings', [
        'ticket_id' => $ticket->id,
        'handled_by_id' => $handler->id,
        'notes' => 'Koneksi kabel daya diperiksa dan dikencangkan.',
    ]);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->completed_at)->toBeNull()
        ->and($ticket->statusHistories()->count())->toBe(0);
});

test('handling cannot skip from classified directly to completed', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diklasifikasi]);
    actingAsTicketHandler($ticket);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Tidak boleh langsung selesai.',
        'status' => TicketStatus::Terselesaikan->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
    ])
        ->assertConflict()
        ->assertJsonPath('message', 'Tiket harus berstatus diproses untuk menerima penanganan.');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->statusHistories()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diklasifikasi);
});

test('handling history keeps multiple notes linked to the same ticket', function (): void {
    $handler = User::factory()->for(Unit::factory())->create();
    $ticket = Ticket::factory()->create([
        'reporter_id' => $handler,
        'status' => TicketStatus::Diproses,
    ]);
    actingAsTicketHandler($ticket, $handler, ['tickets-access']);

    foreach (['Pemeriksaan awal.', 'Penggantian komponen.'] as $notes) {
        $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
            'notes' => $notes,
            'status' => TicketStatus::Diproses->value,
            'started_at' => '2026-09-11 08:00:00',
            'completed_at' => '2026-09-11 09:00:00',
        ])->assertOk();
    }

    expect($ticket->handlings()->count())->toBe(2)
        ->and($ticket->statusHistories()->count())->toBe(0);

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.handlings')
        ->assertJsonPath('data.handlings.0.notes', 'Penggantian komponen.')
        ->assertJsonPath('data.handlings.1.notes', 'Pemeriksaan awal.');
});

test('a processed ticket can be completed atomically without resetting its SLA', function (): void {
    $handler = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Diproses,
        'sla_deadline' => '2026-09-12 08:00:00',
    ]);
    actingAsTicketHandler($ticket, $handler);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Perbaikan selesai dan perangkat sudah diuji.',
        'status' => TicketStatus::Terselesaikan->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:30:00',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'terselesaikan')
        ->assertJsonPath('data.handlings.0.status', 'terselesaikan');

    $handling = TicketHandling::query()->sole();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Terselesaikan)
        ->and($ticket->completed_at?->format('Y-m-d H:i:s'))->toBe('2026-09-11 09:30:00')
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-09-12 08:00:00')
        ->and($handling->ticket_id)->toBe($ticket->id)
        ->and($handling->completed_at?->format('Y-m-d H:i:s'))->toBe('2026-09-11 09:30:00');
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Diproses->value,
        'to_status' => TicketStatus::Terselesaikan->value,
        'changed_by_id' => $handler->id,
    ]);
});

test('start and completion times are required when handling a ticket', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Pekerjaan dinyatakan selesai.',
        'status' => TicketStatus::Terselesaikan->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['started_at', 'completed_at']);

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->completed_at)->toBeNull();
});

test('handling notes and status are required', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notes', 'status', 'started_at', 'completed_at']);

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses);
});

test('handling only accepts in progress or completed as its target status', function (string $status): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan penanganan.',
        'status' => $status,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses);
})->with([
    'new' => [TicketStatus::Baru->value],
    'closed' => [TicketStatus::Ditutup->value],
    'rejected' => [TicketStatus::Ditolak->value],
    'unknown' => ['tertunda'],
]);

test('a ticket outside in progress cannot receive handling updates', function (TicketStatus $status): void {
    $ticket = Ticket::factory()->create(['status' => $status]);
    actingAsTicketHandler($ticket);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan yang tidak boleh tersimpan.',
        'status' => TicketStatus::Terselesaikan->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:30:00',
    ])
        ->assertConflict()
        ->assertJsonPath('message', 'Tiket harus berstatus diproses untuk menerima penanganan.');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe($status);
})->with([
    'new' => [TicketStatus::Baru],
    'closed' => [TicketStatus::Ditutup],
    'completed' => [TicketStatus::Terselesaikan],
    'rejected' => [TicketStatus::Ditolak],
]);

test('completion time cannot be before start time', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Waktu tidak konsisten.',
        'status' => TicketStatus::Terselesaikan->value,
        'started_at' => '2026-09-11 10:00:00',
        'completed_at' => '2026-09-11 09:00:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('completed_at');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->completed_at)->toBeNull();
});

test('a result photo is stored through the configured filesystem with portable metadata', function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $handler = User::factory()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket, $handler);

    $response = $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Foto hasil penggantian komponen.',
        'status' => TicketStatus::Diproses->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
        'result_photo' => UploadedFile::fake()->image('hasil-perbaikan.jpg')->size(512),
    ], ['Accept' => 'application/json']);

    $response
        ->assertOk()
        ->assertJsonPath('data.handlings.0.result_photo.original_name', 'hasil-perbaikan.jpg')
        ->assertJsonPath('data.handlings.0.result_photo.mime_type', 'image/jpeg')
        ->assertJsonMissingPath('data.handlings.0.result_photo.object_key')
        ->assertJsonMissingPath('data.handlings.0.result_photo.url');

    $handling = TicketHandling::query()->whereBelongsTo($ticket)->sole();
    $objectKey = $handling->result_photo_object_key;

    expect($objectKey)->toStartWith('helpdesk/handling-results/');
    Storage::disk('local')->assertExists($objectKey);

    $this->assertDatabaseHas('ticket_handlings', [
        'ticket_id' => $ticket->id,
        'result_photo_object_key' => $objectKey,
        'result_photo_original_name' => 'hasil-perbaikan.jpg',
        'result_photo_mime_type' => 'image/jpeg',
    ]);
});

test('result photo is cleaned up when ticket state makes the operation fail', function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Baru]);
    actingAsTicketHandler($ticket);

    $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan tidak valid.',
        'status' => TicketStatus::Diproses->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
        'result_photo' => UploadedFile::fake()->image('tidak-tersimpan.jpg'),
    ], ['Accept' => 'application/json'])->assertConflict();

    expect(Storage::disk('local')->allFiles('helpdesk/handling-results'))->toBeEmpty()
        ->and($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Baru);
});

test('history status and result photo are rolled back together when the ticket update fails', function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket);

    Event::listen('eloquent.updating: '.Ticket::class, function (Ticket $updatingTicket): void {
        if ($updatingTicket->isDirty('status')) {
            throw new RuntimeException('Simulated ticket update failure.');
        }
    });

    try {
        $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
            'notes' => 'Catatan yang harus ikut dibatalkan.',
            'status' => TicketStatus::Terselesaikan->value,
            'started_at' => '2026-09-11 08:00:00',
            'completed_at' => '2026-09-11 09:30:00',
            'result_photo' => UploadedFile::fake()->image('rollback.jpg'),
        ], ['Accept' => 'application/json'])->assertInternalServerError();
    } finally {
        Event::forget('eloquent.updating: '.Ticket::class);
    }

    expect(TicketHandling::query()->whereBelongsTo($ticket)->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->completed_at)->toBeNull()
        ->and(Storage::disk('local')->allFiles('helpdesk/handling-results'))->toBeEmpty();
});

test('result photo must be an image no larger than two megabytes', function (UploadedFile $file): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($ticket);

    $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan penanganan.',
        'status' => TicketStatus::Diproses->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
        'result_photo' => $file,
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('result_photo');
})->with([
    'non image file' => fn (): UploadedFile => UploadedFile::fake()->create('hasil.pdf', 100, 'application/pdf'),
    'image above size limit' => fn (): UploadedFile => UploadedFile::fake()->image('besar.jpg')->size(2049),
]);

test('guest cannot add ticket handling history', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan penanganan.',
        'status' => TicketStatus::Diproses->value,
    ])->assertUnauthorized();
});

test('authenticated user without tickets-handle permission cannot add ticket handling history', function (): void {
    $this->seed(DomainPermissionSeeder::class);
    $handler = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']);
    $handler->assignRole($role);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $handler->id,
    ]);
    Sanctum::actingAs($handler);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan tanpa izin.',
        'status' => TicketStatus::Diproses->value,
    ])->assertForbidden();

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses);
});

test('an actor other than the assigned officer cannot handle a ticket', function (): void {
    $assignedOfficer = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $assignedOfficer->id,
    ]);
    actingAsTicketHandler(null);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan oleh petugas lain.',
        'status' => TicketStatus::Diproses->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
    ])->assertForbidden();

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->statusHistories()->count())->toBe(0);
});

test('ticket officer roles receive handling permission and can handle their assigned ticket', function (
    TicketService $service,
    string $roleName,
): void {
    $this->seed([
        CorePermissionSeeder::class,
        DomainPermissionSeeder::class,
        RoleSeeder::class,
    ]);
    $handler = User::factory()->create();
    $handler->assignRole($roleName);
    $ticket = Ticket::factory()->create([
        'service' => $service,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $handler->id,
    ]);
    Sanctum::actingAs($handler);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan oleh petugas yang ditugaskan.',
        'status' => TicketStatus::Diproses->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
    ])->assertOk();

    expect($handler->can('tickets-handle'))->toBeTrue();
    $this->assertDatabaseHas('ticket_handlings', [
        'ticket_id' => $ticket->id,
        'handled_by_id' => $handler->id,
        'notes' => 'Catatan oleh petugas yang ditugaskan.',
    ]);
})->with([
    'TIK officer' => [TicketService::Tik, 'petugas-tik'],
    'Sarpras officer' => [TicketService::Sarpras, 'petugas-sarpras'],
]);

test('handling endpoint returns 404 when ticket does not exist', function (): void {
    actingAsTicketHandler();

    $this->postJson('/api/v1/tickets/999999/handlings', [
        'notes' => 'Catatan penanganan.',
        'status' => TicketStatus::Diproses->value,
    ])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
