<?php

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\ItTag;
use App\Models\QualityCategory;
use App\Models\SarprasCategory;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'ticket_number' => 'TIK-2026-000001',
        'created_at' => '2026-09-09 08:00:00',
    ]);
    Ticket::factory()->for($reporter, 'reporter')->for($unit)->create([
        'ticket_number' => 'SPR-2026-000002',
        'service' => TicketService::Sarpras,
        'created_at' => '2026-09-10 08:00:00',
    ]);

    $this->getJson('/api/v1/tickets?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.ticket_number', 'SPR-2026-000002')
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
                    'tik_detail',
                    'sarpras_detail',
                    'description',
                    'status',
                    'rejection_reason',
                    'priority',
                    'asset',
                    'reporter' => ['id', 'name'],
                    'unit' => ['id', 'name'],
                    'assigned_officer',
                    'classified_by',
                    'initial_evidence',
                    'classified_at',
                    'assigned_at',
                    'sla_started_at',
                    'sla_deadline',
                    'completed_at',
                    'closed_at',
                    'created_at',
                    'updated_at',
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
        'ticket_number' => 'TIK-2026-000001',
        'service' => TicketService::Tik,
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'SPR-2026-000001',
        'service' => TicketService::Sarpras,
    ]);

    $this->getJson('/api/v1/tickets?service=tik')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-000001');
});

test('it filters tickets by status', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000001',
        'status' => TicketStatus::Baru,
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000002',
        'status' => TicketStatus::Terselesaikan,
    ]);

    $this->getJson('/api/v1/tickets?status=terselesaikan')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-000002');
});

test('it filters tickets by priority', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000001',
        'priority' => TicketPriority::High,
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000002',
        'priority' => TicketPriority::Low,
    ]);

    $this->getJson('/api/v1/tickets?priority=high')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-000001');
});

test('it filters tickets by an inclusive date range', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000001',
        'created_at' => '2026-09-08 23:59:59',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000002',
        'created_at' => '2026-09-09 00:00:00',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000003',
        'created_at' => '2026-09-10 23:59:59',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000004',
        'created_at' => '2026-09-11 00:00:00',
    ]);

    $this->getJson('/api/v1/tickets?date_from=2026-09-09&date_to=2026-09-10')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-000003')
        ->assertJsonPath('data.1.ticket_number', 'TIK-2026-000002');
});

test('it searches ticket number classification and description', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    $qualityCategory = QualityCategory::create([
        'name' => 'Ketidaksesuaian Program',
        'is_active' => true,
    ]);
    $itTag = ItTag::create([
        'name' => 'Perangkat Komputer',
        'is_active' => true,
    ]);
    $sarprasCategory = SarprasCategory::create([
        'name' => 'Air Conditioner',
        'is_active' => true,
    ]);
    actingAsTicketReader($user);

    $tikTicket = Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000042',
        'description' => 'Perangkat tidak dapat menyala.',
    ]);
    $tikTicket->tikDetail()->create([
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $itTag->id,
    ]);

    $sarprasTicket = Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'SPR-2026-000001',
        'description' => 'Ruangan terasa panas.',
        'service' => TicketService::Sarpras,
    ]);
    $sarprasTicket->sarprasDetail()->create([
        'sarpras_category_id' => $sarprasCategory->id,
    ]);

    $this->getJson('/api/v1/tickets?search=komputer')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-000042');
});

test('it binds ticket search input as a value', function (): void {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    actingAsTicketReader($user);

    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000042',
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
        'ticket_number' => 'TIK-2026-000100',
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
        'description' => 'Printer tidak dapat mencetak.',
        'created_at' => '2026-09-10 09:00:00',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'TIK-2026-000101',
        'service' => TicketService::Tik,
        'status' => TicketStatus::Terselesaikan,
        'description' => 'Printer tidak dapat mencetak.',
        'created_at' => '2026-09-10 09:00:00',
    ]);
    Ticket::factory()->for($user, 'reporter')->for($unit)->create([
        'ticket_number' => 'SPR-2026-000102',
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
        'description' => 'Printer tidak dapat mencetak.',
        'created_at' => '2026-09-10 09:00:00',
    ]);

    $this->getJson('/api/v1/tickets?service=tik&status=baru&date_from=2026-09-10&date_to=2026-09-10&search=Printer')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', 'TIK-2026-000100');
});

test('it returns ticket details with reporter and unit summaries', function (): void {
    $reporter = User::factory()->create(['name' => 'Dedi Pelapor']);
    $unit = Unit::factory()->create(['unit_name' => 'Unit Radiologi']);
    $sarprasCategory = SarprasCategory::create([
        'name' => 'Kelistrikan',
        'is_active' => true,
    ]);
    $ticket = Ticket::factory()->for($reporter, 'reporter')->for($unit)->create([
        'ticket_number' => 'SPR-2026-000007',
        'service' => TicketService::Sarpras,
        'description' => 'Lampu ruang pemeriksaan mati.',
        'status' => TicketStatus::Ditutup,
    ]);
    $ticket->sarprasDetail()->create([
        'sarpras_category_id' => $sarprasCategory->id,
    ]);
    actingAsTicketReader($reporter);

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $ticket->id,
                'ticket_number' => 'SPR-2026-000007',
                'service' => 'sarpras',
                'category' => 'Kelistrikan',
                'tik_detail' => null,
                'sarpras_detail' => [
                    'sarpras_category' => [
                        'id' => $sarprasCategory->id,
                        'name' => 'Kelistrikan',
                    ],
                ],
                'description' => 'Lampu ruang pemeriksaan mati.',
                'status' => 'ditutup',
                'rejection_reason' => null,
                'priority' => null,
                'asset' => null,
                'reporter' => [
                    'id' => $reporter->id,
                    'name' => 'Dedi Pelapor',
                ],
                'unit' => [
                    'id' => $unit->id,
                    'name' => 'Unit Radiologi',
                ],
                'assigned_officer' => null,
                'classified_by' => null,
                'initial_evidence' => null,
                'classified_at' => null,
                'assigned_at' => null,
                'sla_started_at' => null,
                'sla_deadline' => null,
                'completed_at' => null,
                'closed_at' => null,
                'handlings' => [],
                'status_histories' => [],
                'created_at' => $ticket->created_at->toJSON(),
                'updated_at' => $ticket->updated_at->toJSON(),
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
    'unknown priority' => [['priority' => 'urgent'], 'priority'],
    'invalid start date' => [['date_from' => '10-09-2026'], 'date_from'],
    'end date before start date' => [[
        'date_from' => '2026-09-10',
        'date_to' => '2026-09-09',
    ], 'date_to'],
    'page below one' => [['page' => 0], 'page'],
    'per page above maximum' => [['per_page' => 101], 'per_page'],
]);

test('ticket list applies the actor scope before filters', function (): void {
    $reporter = User::factory()->for(Unit::factory())->create();
    $tikOfficer = User::factory()->for(Unit::factory())->create();
    $sarprasOfficer = User::factory()->create();
    $coordinator = User::factory()->create();
    $management = User::factory()->create();
    $superAdmin = User::factory()->create();

    foreach (['petugas-tik', 'petugas-sarpras', 'koordinator-sarpras', 'user', 'management', 'super-admin'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }

    $reporter->assignRole('user');
    $tikOfficer->assignRole('petugas-tik');
    $sarprasOfficer->assignRole('petugas-sarpras');
    $coordinator->assignRole('koordinator-sarpras');
    $management->assignRole('management');
    $superAdmin->assignRole('super-admin');

    $ownSarpras = Ticket::factory()->for($tikOfficer, 'reporter')->create([
        'ticket_number' => 'SPR-2026-000001',
        'service' => TicketService::Sarpras,
    ]);
    $assignedTik = Ticket::factory()->create([
        'ticket_number' => 'TIK-2026-000002',
        'service' => TicketService::Tik,
        'reporter_id' => $reporter->id,
        'assigned_officer_id' => $tikOfficer->id,
    ]);
    $assignedSarpras = Ticket::factory()->create([
        'ticket_number' => 'SPR-2026-000003',
        'service' => TicketService::Sarpras,
        'reporter_id' => $reporter->id,
        'assigned_officer_id' => $sarprasOfficer->id,
    ]);
    $unrelatedTik = Ticket::factory()->create([
        'ticket_number' => 'TIK-2026-000004',
        'service' => TicketService::Tik,
        'reporter_id' => $reporter->id,
    ]);

    actingAsTicketReader($tikOfficer);
    $this->getJson('/api/v1/tickets?service=sarpras')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['ticket_number' => $ownSarpras->ticket_number])
        ->assertJsonMissing(['ticket_number' => $assignedSarpras->ticket_number]);

    actingAsTicketReader($sarprasOfficer);
    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['ticket_number' => $assignedSarpras->ticket_number]);

    actingAsTicketReader($coordinator);
    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['ticket_number' => $ownSarpras->ticket_number])
        ->assertJsonFragment(['ticket_number' => $assignedSarpras->ticket_number])
        ->assertJsonMissing(['ticket_number' => $assignedTik->ticket_number]);

    actingAsTicketReader($reporter);
    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonMissing(['ticket_number' => $ownSarpras->ticket_number]);

    foreach ([$management, $superAdmin] as $globalReader) {
        actingAsTicketReader($globalReader);
        $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['ticket_number' => $unrelatedTik->ticket_number]);
    }
});

test('ticket detail rejects an actor outside the business view scope', function (): void {
    $reader = User::factory()->create();
    $ticket = Ticket::factory()->create();
    actingAsTicketReader($reader);

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertForbidden()
        ->assertJsonMissing(['ticket_number' => $ticket->ticket_number]);
});
