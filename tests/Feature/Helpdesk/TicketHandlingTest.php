<?php

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHandling;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

function actingAsTicketHandlingUser(
    ?Ticket $ticket = null,
    ?string $roleName = null,
    bool $withPermission = true,
): User {
    $user = User::factory()->create();

    if ($roleName !== null) {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $user->assignRole($role);
    }

    if ($withPermission) {
        $permission = Permission::firstOrCreate([
            'name' => 'tickets-handle',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);
    }

    $ticket?->update(['assigned_officer_id' => $user->id]);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * @return array{notes: string, status: string, started_at: string, completed_at: string}
 */
function validTicketHandlingPayload(TicketStatus $status = TicketStatus::Diproses): array
{
    return [
        'notes' => 'Koneksi diperbaiki.',
        'status' => $status->value,
        'started_at' => '2026-09-11 08:00:00',
        'completed_at' => '2026-09-11 09:00:00',
    ];
}

test('guest tidak dapat menangani tiket', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/handlings",
        validTicketHandlingPayload(),
    )->assertUnauthorized();
});

test('user terautentikasi tanpa permission tickets-handle tidak dapat menangani tiket', function (): void {
    Permission::firstOrCreate([
        'name' => 'tickets-handle',
        'guard_name' => 'web',
    ]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
    ]);
    actingAsTicketHandlingUser($ticket, 'petugas-tik', withPermission: false);

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/handlings",
        validTicketHandlingPayload(),
    )->assertForbidden();

    expect($ticket->handlings()->count())->toBe(0);
});

test('super admin dapat menangani tiket TIK dan Sarpras tanpa harus ditugaskan', function (): void {
    $assignedOfficer = User::factory()->create();
    $tikTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $assignedOfficer->id,
    ]);
    $sarprasTicket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $assignedOfficer->id,
    ]);
    actingAsTicketHandlingUser(roleName: 'super-admin');

    $this->postJson(
        "/api/v1/tickets/{$tikTicket->id}/handlings",
        validTicketHandlingPayload(),
    )->assertOk();
    $this->postJson(
        "/api/v1/tickets/{$sarprasTicket->id}/handlings",
        validTicketHandlingPayload(),
    )->assertOk();

    expect($tikTicket->handlings()->count())->toBe(1)
        ->and($sarprasTicket->handlings()->count())->toBe(1);
});

test('koordinator Sarpras dapat menangani tiket Sarpras tanpa harus ditugaskan', function (): void {
    $assignedOfficer = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $assignedOfficer->id,
    ]);
    $coordinator = actingAsTicketHandlingUser(roleName: 'koordinator-sarpras');

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/handlings",
        validTicketHandlingPayload(),
    )
        ->assertOk()
        ->assertJsonPath('data.handlings.0.handled_by.id', $coordinator->id);
});

test('koordinator Sarpras tidak dapat menangani tiket TIK', function (): void {
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
    ]);
    actingAsTicketHandlingUser($ticket, 'koordinator-sarpras');

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/handlings",
        validTicketHandlingPayload(),
    )->assertForbidden();

    expect($ticket->handlings()->count())->toBe(0);
});

test('hanya tiket berstatus diproses yang dapat ditangani', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diklasifikasi]);
    actingAsTicketHandlingUser($ticket, 'super-admin');

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/handlings",
        validTicketHandlingPayload(TicketStatus::Terselesaikan),
    )
        ->assertConflict()
        ->assertJsonPath('message', 'Tiket harus berstatus diproses untuk menerima penanganan.');

    expect($ticket->handlings()->count())->toBe(0)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Diklasifikasi);
});

test('status penanganan hanya dapat tetap diproses atau berubah menjadi terselesaikan', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Diproses,
        'sla_deadline' => '2026-09-12 08:00:00',
    ]);
    $handler = actingAsTicketHandlingUser($ticket, 'super-admin');

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/handlings",
        validTicketHandlingPayload(),
    )
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Diproses->value);

    $completionPayload = validTicketHandlingPayload(TicketStatus::Terselesaikan);
    $completionPayload['completed_at'] = '2026-09-11 09:30:00';
    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/handlings",
        $completionPayload,
    )
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Terselesaikan->value);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Terselesaikan)
        ->and($ticket->completed_at?->format('Y-m-d H:i:s'))->toBe('2026-09-11 09:30:00')
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-09-12 08:00:00')
        ->and($ticket->handlings()->count())->toBe(2)
        ->and($ticket->statusHistories()->count())->toBe(1);
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Diproses->value,
        'to_status' => TicketStatus::Terselesaikan->value,
        'changed_by_id' => $handler->id,
    ]);

    $invalidTicket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    $invalidTicket->update(['assigned_officer_id' => $handler->id]);
    $invalidPayload = validTicketHandlingPayload();
    $invalidPayload['status'] = 'tertunda';

    $this->postJson(
        "/api/v1/tickets/{$invalidTicket->id}/handlings",
        $invalidPayload,
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('notes status started_at dan completed_at wajib diisi', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandlingUser($ticket, 'super-admin');

    $this->postJson("/api/v1/tickets/{$ticket->id}/handlings")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notes', 'status', 'started_at', 'completed_at']);

    expect($ticket->handlings()->count())->toBe(0);
});

test('result_photo bersifat opsional dan harus berupa gambar maksimal 5 mb', function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    actingAsTicketHandlingUser($ticket, 'super-admin');

    foreach ([
        UploadedFile::fake()->create('hasil.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->image('terlalu-besar.jpg')->size(5121),
    ] as $invalidPhoto) {
        $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
            ...validTicketHandlingPayload(),
            'result_photo' => $invalidPhoto,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('result_photo');
    }

    $this->post("/api/v1/tickets/{$ticket->id}/handlings", [
        ...validTicketHandlingPayload(),
        'result_photo' => UploadedFile::fake()->image('hasil-perbaikan.jpg')->size(512),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.handlings.0.result_photo.original_name', 'hasil-perbaikan.jpg')
        ->assertJsonPath('data.handlings.0.result_photo.mime_type', 'image/jpeg')
        ->assertJsonMissingPath('data.handlings.0.result_photo.object_key');

    $handling = TicketHandling::query()->whereBelongsTo($ticket)->sole();
    expect($handling->result_photo_object_key)->toStartWith('helpdesk/handling-results/');
    Storage::disk('local')->assertExists($handling->result_photo_object_key);
});

test('endpoint penanganan mengembalikan 404 ketika tiket tidak ditemukan', function (): void {
    actingAsTicketHandlingUser();

    $this->postJson(
        '/api/v1/tickets/999999/handlings',
        validTicketHandlingPayload(),
    )
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
