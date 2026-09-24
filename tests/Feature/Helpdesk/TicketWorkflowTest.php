<?php

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\ItTag;
use App\Models\QualityCategory;
use App\Models\SarprasCategory;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DomainPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsTicketWorkflowUser(string $permissionName, ?string $roleName = null): User
{
    $user = User::factory()->create();
    $permission = Permission::firstOrCreate([
        'name' => $permissionName,
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    if ($roleName !== null) {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $user->assignRole($role);
    }

    Sanctum::actingAs($user);

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createTicketOfficer(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);
    $officer = User::factory()->create([
        'status' => 'active',
        'status_user' => 'Aktif',
        ...$attributes,
    ]);
    $officer->assignRole($role);

    return $officer;
}

test('super admin classifies a new TIK ticket without priority or SLA', function (): void {
    $actor = actingAsTicketWorkflowUser('tickets-verify', 'super-admin');
    $qualityCategory = QualityCategory::create(['name' => 'Ketidakstabilan System', 'is_active' => true]);
    $itTag = ItTag::create(['name' => 'Server', 'is_active' => true]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    try {
        Carbon::setTestNow('2026-09-24 08:00:00');

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
            'quality_category_id' => $qualityCategory->id,
            'it_tag_id' => $itTag->id,
        ]);
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Tiket berhasil diklasifikasi.')
        ->assertJsonPath('data.status', TicketStatus::Diklasifikasi->value)
        ->assertJsonPath('data.priority', null)
        ->assertJsonPath('data.sla_deadline', null)
        ->assertJsonPath('data.classified_by.id', $actor->id)
        ->assertJsonPath('data.tik_detail.quality_category.id', $qualityCategory->id)
        ->assertJsonPath('data.tik_detail.it_tag.id', $itTag->id);

    $ticket->refresh();

    expect($ticket->classified_at?->format('Y-m-d H:i:s'))->toBe('2026-09-24 08:00:00')
        ->and($ticket->priority)->toBeNull()
        ->and($ticket->sla_deadline)->toBeNull();
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Baru->value,
        'to_status' => TicketStatus::Diklasifikasi->value,
        'changed_by_id' => $actor->id,
    ]);
});

test('Sarpras coordinator classifies a new Sarpras ticket', function (): void {
    $actor = actingAsTicketWorkflowUser('tickets-verify', 'koordinator-sarpras');
    $category = SarprasCategory::create(['name' => 'Alkes', 'is_active' => true]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'sarpras_category_id' => $category->id,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Diklasifikasi->value)
        ->assertJsonPath('data.priority', null)
        ->assertJsonPath('data.sla_deadline', null)
        ->assertJsonPath('data.classified_by.id', $actor->id)
        ->assertJsonPath('data.sarpras_detail.sarpras_category.id', $category->id);

    expect($ticket->refresh()->priority)->toBeNull()
        ->and($ticket->sla_deadline)->toBeNull();
});

test('classification requires custom text for the Lain-lain IT tag', function (): void {
    actingAsTicketWorkflowUser('tickets-verify', 'super-admin');
    $qualityCategory = QualityCategory::create(['name' => 'Ketidaksesuaian Program', 'is_active' => true]);
    $otherTag = ItTag::create(['name' => 'Lain-lain', 'is_active' => true]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $otherTag->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('custom_it_tag_text');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Baru)
        ->and($ticket->tikDetail)->toBeNull()
        ->and($ticket->statusHistories()->count())->toBe(0);
});

test('classification rejects actors and tickets outside the service and state rules', function (): void {
    actingAsTicketWorkflowUser('tickets-verify', 'koordinator-sarpras');
    $qualityCategory = QualityCategory::create(['name' => 'Ketidakstabilan System', 'is_active' => true]);
    $itTag = ItTag::create(['name' => 'Server', 'is_active' => true]);
    $tikTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$tikTicket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $itTag->id,
    ])->assertForbidden();

    actingAsTicketWorkflowUser('tickets-verify', 'super-admin');
    $classifiedTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$classifiedTicket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $itTag->id,
    ])
        ->assertConflict()
        ->assertJsonPath('message', 'Status tiket tidak dapat diubah dari diklasifikasi menjadi diklasifikasi.');

    expect($classifiedTicket->refresh()->priority)->toBeNull()
        ->and($classifiedTicket->tikDetail)->toBeNull();
});

test('new ticket can be rejected with a reason', function (): void {
    actingAsTicketWorkflowUser('tickets-reject', 'super-admin');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/reject", [
        'reason' => 'Informasi kerusakan tidak sesuai.',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Tiket berhasil ditolak.')
        ->assertJsonPath('data.status', TicketStatus::Ditolak->value)
        ->assertJsonPath('data.rejection_reason', 'Informasi kerusakan tidak sesuai.');
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'status' => TicketStatus::Ditolak->value,
    ]);
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Baru->value,
        'to_status' => TicketStatus::Ditolak->value,
        'notes' => 'Informasi kerusakan tidak sesuai.',
    ]);
});

test('reject returns 422 when reason is missing', function (): void {
    actingAsTicketWorkflowUser('tickets-reject', 'super-admin');
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Baru]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/reject");

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
    expect($ticket->refresh()->status)->toBe(TicketStatus::Baru)
        ->and($ticket->statusHistories()->count())->toBe(0);
});

test('reject returns 409 when ticket is not new', function (TicketStatus $status): void {
    actingAsTicketWorkflowUser('tickets-reject', 'super-admin');
    $ticket = Ticket::factory()->create(['status' => $status]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/reject", [
        'reason' => 'Tidak dapat diproses.',
    ]);

    $response
        ->assertConflict()
        ->assertJsonPath('message', "Status tiket tidak dapat diubah dari {$status->value} menjadi ditolak.");
    expect($ticket->refresh()->status)->toBe($status)
        ->and($ticket->statusHistories()->count())->toBe(0);
})->with([
    'verified' => TicketStatus::Terverifikasi,
    'in progress' => TicketStatus::Diproses,
    'completed' => TicketStatus::Terselesaikan,
]);

test('Sarpras coordinator can reject Sarpras but not TIK tickets', function (): void {
    actingAsTicketWorkflowUser('tickets-reject', 'koordinator-sarpras');
    $sarprasTicket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
    ]);
    $tikTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$sarprasTicket->id}/reject", [
        'reason' => 'Layanan tidak sesuai.',
    ])->assertOk();

    $this->postJson("/api/v1/tickets/{$tikTicket->id}/reject", [
        'reason' => 'Tidak boleh diproses koordinator.',
    ])->assertForbidden();

    expect($sarprasTicket->refresh()->status)->toBe(TicketStatus::Ditolak)
        ->and($tikTicket->refresh()->status)->toBe(TicketStatus::Baru);
});

test('super admin assigns a TIK officer with priority and SLA', function (): void {
    $actor = actingAsTicketWorkflowUser('tickets-assign', 'super-admin');
    $officer = createTicketOfficer('petugas-tik', ['name' => 'Petugas TIK Terpilih']);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    try {
        Carbon::setTestNow('2026-09-24 08:00:00');
        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assigned_officer_id' => $officer->id,
            'priority' => TicketPriority::Critical->value,
        ]);
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Petugas berhasil ditugaskan.')
        ->assertJsonPath('data.priority', TicketPriority::Critical->value)
        ->assertJsonPath('data.assigned_officer.id', $officer->id)
        ->assertJsonPath('data.status', TicketStatus::Ditugaskan->value);

    $ticket->refresh();
    expect($ticket->assigned_at?->format('Y-m-d H:i:s'))->toBe('2026-09-24 08:00:00')
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-09-24 10:00:00');
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Diklasifikasi->value,
        'to_status' => TicketStatus::Ditugaskan->value,
        'changed_by_id' => $actor->id,
    ]);
});

test('Sarpras coordinator assigns only a Sarpras officer', function (): void {
    actingAsTicketWorkflowUser('tickets-assign', 'koordinator-sarpras');
    $sarprasOfficer = createTicketOfficer('petugas-sarpras');
    $tikOfficer = createTicketOfficer('petugas-tik');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assigned_officer_id' => $tikOfficer->id,
        'priority' => TicketPriority::High->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assigned_officer_id');

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assigned_officer_id' => $sarprasOfficer->id,
        'priority' => TicketPriority::High->value,
    ])->assertOk();

    expect($ticket->refresh()->assigned_officer_id)->toBe($sarprasOfficer->id)
        ->and($ticket->priority)->toBe(TicketPriority::High);
});

test('assignment authorization follows the ticket service', function (): void {
    $coordinator = actingAsTicketWorkflowUser('tickets-assign', 'koordinator-sarpras');
    $tikOfficer = createTicketOfficer('petugas-tik');
    $tikTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$tikTicket->id}/assign", [
        'assigned_officer_id' => $tikOfficer->id,
        'priority' => TicketPriority::Low->value,
    ])->assertForbidden();

    $superAdmin = actingAsTicketWorkflowUser('tickets-assign', 'super-admin');
    $sarprasOfficer = createTicketOfficer('petugas-sarpras');
    $sarprasTicket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$sarprasTicket->id}/assign", [
        'assigned_officer_id' => $sarprasOfficer->id,
        'priority' => TicketPriority::Low->value,
    ])->assertForbidden();

    expect($coordinator->id)->not->toBe($superAdmin->id);
});

test('assigned ticket can be reassigned without resetting priority SLA or history', function (): void {
    actingAsTicketWorkflowUser('tickets-assign', 'super-admin');
    $oldOfficer = createTicketOfficer('petugas-tik');
    $newOfficer = createTicketOfficer('petugas-tik');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Ditugaskan,
        'assigned_officer_id' => $oldOfficer->id,
        'assigned_at' => '2026-09-24 08:00:00',
        'priority' => TicketPriority::High,
        'sla_deadline' => '2026-09-24 12:00:00',
    ]);

    try {
        Carbon::setTestNow('2026-09-24 09:00:00');
        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assigned_officer_id' => $newOfficer->id,
        ]);
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Petugas berhasil ditugaskan ulang.');
    $ticket->refresh();
    expect($ticket->assigned_officer_id)->toBe($newOfficer->id)
        ->and($ticket->assigned_at?->format('Y-m-d H:i:s'))->toBe('2026-09-24 09:00:00')
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-09-24 12:00:00')
        ->and($ticket->statusHistories()->count())->toBe(0);
});

test('processed ticket returns to assigned when reassigned without resetting SLA', function (): void {
    $actor = actingAsTicketWorkflowUser('tickets-assign', 'super-admin');
    $oldOfficer = createTicketOfficer('petugas-tik');
    $newOfficer = createTicketOfficer('petugas-tik');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $oldOfficer->id,
        'priority' => TicketPriority::Medium,
        'sla_deadline' => '2026-09-25 08:00:00',
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assigned_officer_id' => $newOfficer->id,
    ])->assertOk();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Ditugaskan)
        ->and($ticket->assigned_officer_id)->toBe($newOfficer->id)
        ->and($ticket->priority)->toBe(TicketPriority::Medium)
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-09-25 08:00:00');
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Diproses->value,
        'to_status' => TicketStatus::Ditugaskan->value,
        'changed_by_id' => $actor->id,
    ]);
});

test('assignee options search active officers by service name and jabatan', function (): void {
    actingAsTicketWorkflowUser('tickets-assign', 'super-admin');
    $byName = createTicketOfficer('petugas-tik', ['name' => 'Network Specialist', 'jabatan' => 'Teknisi']);
    $byPosition = createTicketOfficer('petugas-tik', ['name' => 'Budi', 'jabatan' => 'Network Engineer']);
    createTicketOfficer('petugas-sarpras', ['name' => 'Network Sarpras', 'jabatan' => 'Network Engineer']);
    createTicketOfficer('petugas-tik', [
        'name' => 'Network Nonaktif',
        'jabatan' => 'Network Engineer',
        'status_user' => 'Nonaktif',
    ]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->getJson("/api/v1/tickets/{$ticket->id}/assignee-options?search=Network")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $byName->id, 'name' => 'Network Specialist', 'jabatan' => 'Teknisi'])
        ->assertJsonFragment(['id' => $byPosition->id, 'name' => 'Budi', 'jabatan' => 'Network Engineer'])
        ->assertJsonMissing(['name' => 'Network Sarpras'])
        ->assertJsonMissing(['name' => 'Network Nonaktif']);
});

test('assign rejects missing input and unsupported ticket states', function (TicketStatus $status): void {
    actingAsTicketWorkflowUser('tickets-assign', 'super-admin');
    $officer = createTicketOfficer('petugas-tik');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => $status,
    ]);

    $payload = $status === TicketStatus::Diklasifikasi
        ? []
        : ['assigned_officer_id' => $officer->id];
    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", $payload);

    if ($status === TicketStatus::Diklasifikasi) {
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_officer_id', 'priority']);
    } else {
        $response->assertConflict();
    }
})->with([
    'missing first assignment input' => TicketStatus::Diklasifikasi,
    'new' => TicketStatus::Baru,
    'escalated' => TicketStatus::Eskalasi,
    'completed' => TicketStatus::Terselesaikan,
    'verified' => TicketStatus::Terverifikasi,
    'closed' => TicketStatus::Ditutup,
    'rejected' => TicketStatus::Ditolak,
]);

test('guest cannot use ticket workflow endpoints', function (string $endpoint, array $payload): void {
    $ticket = Ticket::factory()->create();

    $this->postJson("/api/v1/tickets/{$ticket->id}/{$endpoint}", $payload)
        ->assertUnauthorized();
})->with([
    'classify' => ['classify', []],
    'reject' => ['reject', ['reason' => 'Tidak dapat diproses.']],
    'assign' => ['assign', ['assigned_officer_id' => 1, 'priority' => 'high']],
]);

test('authenticated user without workflow permission receives 403', function (string $endpoint, array $payload): void {
    $this->seed(DomainPermissionSeeder::class);
    $ticket = Ticket::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$ticket->id}/{$endpoint}", $payload)
        ->assertForbidden();
})->with([
    'classify' => ['classify', []],
    'reject' => ['reject', ['reason' => 'Tidak dapat diproses.']],
    'assign' => ['assign', ['assigned_officer_id' => 1, 'priority' => 'high']],
]);

test('ticket workflow endpoint returns 404 when ticket does not exist', function (string $endpoint, array $payload, string $permission): void {
    actingAsTicketWorkflowUser($permission);

    $this->postJson("/api/v1/tickets/999999/{$endpoint}", $payload)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
})->with([
    'classify' => ['classify', [], 'tickets-verify'],
    'reject' => ['reject', ['reason' => 'Tidak dapat diproses.'], 'tickets-reject'],
    'assign' => ['assign', ['assigned_officer_id' => 999999, 'priority' => 'high'], 'tickets-assign'],
]);
