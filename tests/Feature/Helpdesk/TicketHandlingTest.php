<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHandling;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $additionalPermissions
 */
function actingAsTicketHandler(?User $user = null, array $additionalPermissions = []): User
{
    $user ??= User::factory()->create();

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

test('a processed ticket can receive a handling note without changing its status', function (): void {
    $handler = User::factory()->create(['name' => 'Petugas Penanganan']);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($handler);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Koneksi kabel daya diperiksa dan dikencangkan.',
        'status' => TicketStatus::Diproses->value,
        'started_at' => '2026-09-11 08:00:00',
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
        ->and($ticket->completed_at)->toBeNull();
});

test('handling history keeps multiple notes linked to the same ticket', function (): void {
    $handler = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'reporter_id' => $handler,
        'status' => TicketStatus::Diproses,
    ]);
    actingAsTicketHandler($handler, ['tickets-access']);

    foreach (['Pemeriksaan awal.', 'Penggantian komponen.'] as $notes) {
        $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
            'notes' => $notes,
            'status' => TicketStatus::Diproses->value,
        ])->assertOk();
    }

    expect($ticket->handlings()->count())->toBe(2);

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.handlings')
        ->assertJsonPath('data.handlings.0.notes', 'Penggantian komponen.')
        ->assertJsonPath('data.handlings.1.notes', 'Pemeriksaan awal.');
});

test('a processed ticket can be completed atomically with its handling history', function (): void {
    $handler = User::factory()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler($handler);

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
        ->and($handling->ticket_id)->toBe($ticket->id)
        ->and($handling->completed_at?->format('Y-m-d H:i:s'))->toBe('2026-09-11 09:30:00');
});

test('completion time is required when completing a ticket', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler();

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Pekerjaan dinyatakan selesai.',
        'status' => TicketStatus::Terselesaikan->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('completed_at');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->completed_at)->toBeNull();
});

test('handling notes and status are required', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler();

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notes', 'status']);

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses);
});

test('handling only accepts in progress or completed as its target status', function (string $status): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler();

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan penanganan.',
        'status' => $status,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses);
})->with([
    'new' => TicketStatus::Baru->value,
    'verified' => TicketStatus::Terverifikasi->value,
    'rejected' => TicketStatus::Ditolak->value,
    'unknown' => 'tertunda',
]);

test('a ticket outside in progress cannot receive handling updates', function (TicketStatus $status): void {
    $ticket = Ticket::factory()->create(['status' => $status]);
    actingAsTicketHandler();

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan yang tidak boleh tersimpan.',
        'status' => TicketStatus::Terselesaikan->value,
        'completed_at' => '2026-09-11 09:30:00',
    ])
        ->assertConflict()
        ->assertJsonPath('message', 'Tiket harus berstatus ditugaskan atau diproses untuk menerima penanganan.');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe($status);
})->with([
    'new' => TicketStatus::Baru,
    'verified' => TicketStatus::Terverifikasi,
    'completed' => TicketStatus::Terselesaikan,
    'rejected' => TicketStatus::Ditolak,
]);

test('completion time cannot be before start time', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandler();

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
    actingAsTicketHandler($handler);

    $response = $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Foto hasil penggantian komponen.',
        'status' => TicketStatus::Diproses->value,
        'result_photo' => UploadedFile::fake()->image('hasil-perbaikan.jpg')->size(512),
    ], ['Accept' => 'application/json']);

    $response
        ->assertOk()
        ->assertJsonPath('data.handlings.0.result_photo.original_name', 'hasil-perbaikan.jpg')
        ->assertJsonPath('data.handlings.0.result_photo.mime_type', 'image/jpeg');

    $objectKey = $response->json('data.handlings.0.result_photo.object_key');

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
    actingAsTicketHandler();

    $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan tidak valid.',
        'status' => TicketStatus::Diproses->value,
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
    actingAsTicketHandler();

    Event::listen('eloquent.updating: '.Ticket::class, function (Ticket $updatingTicket): void {
        if ($updatingTicket->isDirty('status')) {
            throw new RuntimeException('Simulated ticket update failure.');
        }
    });

    try {
        $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
            'notes' => 'Catatan yang harus ikut dibatalkan.',
            'status' => TicketStatus::Terselesaikan->value,
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
    actingAsTicketHandler();

    $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan penanganan.',
        'status' => TicketStatus::Diproses->value,
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
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan tanpa izin.',
        'status' => TicketStatus::Diproses->value,
    ])->assertForbidden();

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diproses);
});

test('super admin receives all ticket permissions and can add ticket handling history', function (): void {
    $this->seed([
        CorePermissionSeeder::class,
        DomainPermissionSeeder::class,
        RoleSeeder::class,
    ]);
    $handler = User::factory()->create();
    $handler->assignRole('super-admin');
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    Sanctum::actingAs($handler);

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Catatan oleh super admin.',
        'status' => TicketStatus::Diproses->value,
    ])->assertOk();

    expect($handler->hasAllPermissions([
        'tickets-access',
        'tickets-create',
        'tickets-verify',
        'tickets-reject',
        'tickets-assign',
        'tickets-handle',
    ]))->toBeTrue();
    $this->assertDatabaseHas('ticket_handlings', [
        'ticket_id' => $ticket->id,
        'handled_by_id' => $handler->id,
        'notes' => 'Catatan oleh super admin.',
    ]);
});

test('handling endpoint returns 404 when ticket does not exist', function (): void {
    actingAsTicketHandler();

    $this->postJson('/api/v1/tickets/999999/handlings', [
        'notes' => 'Catatan penanganan.',
        'status' => TicketStatus::Diproses->value,
    ])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
