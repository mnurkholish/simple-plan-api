<?php

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function actingAsTicketReader(?User $user = null): User
{
    $user ??= User::factory()->create();
    $permission = Permission::firstOrCreate([
        'name' => 'tickets-access',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
    Sanctum::actingAs($user);

    return $user;
}

test('it returns 401 when listing tickets without authentication', function (): void {
    $this->getJson('/api/v1/tickets')
        ->assertUnauthorized();
});

test('it returns 401 when viewing a ticket without authentication', function (): void {
    $ticket = Ticket::factory()->create();

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertUnauthorized();
});

test('it returns 403 when listing tickets without tickets-access permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/tickets')
        ->assertForbidden();
});

test('it returns 403 when viewing a ticket without tickets-access permission', function (): void {
    $ticket = Ticket::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertForbidden();
});

test('it returns tickets newest first with pagination metadata', function (): void {
    $reporter = User::factory()->create(['name' => 'Rina Pelapor']);
    $unit = Unit::factory()->create(['unit_name' => 'Unit Rawat Jalan']);
    actingAsTicketReader($reporter);

    Ticket::factory()->for($reporter, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0001',
        'created_at' => '2026-09-09 08:00:00',
    ]);
    Ticket::factory()->for($reporter, 'reporter')->for($unit)->create([
        'ticket_number' => 'SARPRAS-2026-0002',
        'service' => TicketService::Sarpras,
        'created_at' => '2026-09-10 08:00:00',
    ]);

    $this->getJson('/api/v1/tickets?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.ticket_number', 'SARPRAS-2026-0002')
        ->assertJsonPath('data.0.reporter.name', 'Rina Pelapor')
        ->assertJsonPath('data.0.unit.name', 'Unit Rawat Jalan')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonMissingPath('data.0.reporter.email')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'ticket_number',
                    'service',
                    'category',
                    'description',
                    'status',
                    'rejection_reason',
                    'priority',
                    'reporter' => ['id', 'name'],
                    'unit' => ['id', 'name'],
                    'assigned_officer',
                    'initial_evidence',
                    'completed_at',
                    'created_at',
                ],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
        ]);
});

test('it returns an empty paginated ticket collection', function (): void {
    actingAsTicketReader();

    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

test('it filters tickets by service', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0001',
        'service' => TicketService::Tik,
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'SARPRAS-2026-0001',
        'service' => TicketService::Sarpras,
    ]);

    $this->getJson('/api/v1/tickets?service=tik')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-0001');
});

test('it filters tickets by status', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0001',
        'status' => TicketStatus::Baru,
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0002',
        'status' => TicketStatus::Selesai,
    ]);

    $this->getJson('/api/v1/tickets?status=selesai')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-0002');
});

test('it filters tickets by an inclusive date range', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0001',
        'created_at' => '2026-09-08 23:59:59',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0002',
        'created_at' => '2026-09-09 00:00:00',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0003',
        'created_at' => '2026-09-10 23:59:59',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0004',
        'created_at' => '2026-09-11 00:00:00',
    ]);

    $this->getJson('/api/v1/tickets?date_from=2026-09-09&date_to=2026-09-10')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-0003')
        ->assertJsonPath('data.1.ticket_number', 'TIK-2026-0002');
});

test('it searches ticket number category and description', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0042',
        'category' => 'Perangkat Komputer',
        'description' => 'Komputer tidak dapat menyala.',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'SARPRAS-2026-0001',
        'category' => 'Air Conditioner',
        'description' => 'Ruangan terasa panas.',
    ]);

    $this->getJson('/api/v1/tickets?search=komputer')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-0042');
});

test('it binds ticket search input as a value', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0042',
        'description' => 'Komputer tidak dapat menyala.',
    ]);

    $this->getJson('/api/v1/tickets?'.http_build_query(['search' => "' OR 1=1 --"]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('it combines ticket filters', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0100',
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
        'description' => 'Printer tidak dapat mencetak.',
        'created_at' => '2026-09-10 09:00:00',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-0101',
        'service' => TicketService::Tik,
        'status' => TicketStatus::Selesai,
        'description' => 'Printer tidak dapat mencetak.',
        'created_at' => '2026-09-10 09:00:00',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'SARPRAS-2026-0102',
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
        'description' => 'Printer tidak dapat mencetak.',
        'created_at' => '2026-09-10 09:00:00',
    ]);

    $this->getJson('/api/v1/tickets?service=tik&status=baru&date_from=2026-09-10&date_to=2026-09-10&search=Printer')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-0100');
});

test('it returns ticket details with reporter and unit summaries', function (): void {
    $reporter = User::factory()->create(['name' => 'Dedi Pelapor']);
    $unit = Unit::factory()->create(['unit_name' => 'Unit Radiologi']);
    $ticket = Ticket::factory()->for($reporter, 'reporter')->for($unit)->create([
        'ticket_number' => 'SARPRAS-2026-0007',
        'service' => TicketService::Sarpras,
        'category' => 'Kelistrikan',
        'description' => 'Lampu ruang pemeriksaan mati.',
        'status' => TicketStatus::Terverifikasi,
    ]);
    actingAsTicketReader($reporter);

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $ticket->id,
                'ticket_number' => 'SARPRAS-2026-0007',
                'service' => 'sarpras',
                'category' => 'Kelistrikan',
                'description' => 'Lampu ruang pemeriksaan mati.',
                'status' => 'terverifikasi',
                'rejection_reason' => null,
                'priority' => null,
                'reporter' => [
                    'id' => $reporter->id,
                    'name' => 'Dedi Pelapor',
                ],
                'unit' => [
                    'id' => $unit->id,
                    'name' => 'Unit Radiologi',
                ],
                'assigned_officer' => null,
                'initial_evidence' => null,
                'completed_at' => null,
                'handlings' => [],
                'created_at' => $ticket->created_at->toJSON(),
            ],
        ]);
});

test('it returns 404 when a ticket does not exist', function (): void {
    actingAsTicketReader();

    $this->getJson('/api/v1/tickets/999999')
        ->assertNotFound()
        ->assertExactJson([
            'message' => 'Resource not found.',
        ]);
});

test('it returns 422 for invalid ticket filters', function (array $query, string $field): void {
    actingAsTicketReader();

    $this->getJson('/api/v1/tickets?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown service' => [['service' => 'umum'], 'service'],
    'unknown status' => [['status' => 'tertunda'], 'status'],
    'invalid start date' => [['date_from' => '10-09-2026'], 'date_from'],
    'end date before start date' => [[
        'date_from' => '2026-09-10',
        'date_to' => '2026-09-09',
    ], 'date_to'],
    'page below one' => [['page' => 0], 'page'],
    'per page above maximum' => [['per_page' => 101], 'per_page'],
]);
