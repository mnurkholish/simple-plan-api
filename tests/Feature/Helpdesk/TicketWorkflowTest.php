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

test('super admin classifies a new TIK ticket with priority SLA detail and history', function (): void {
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
            'priority' => TicketPriority::Critical->value,
        ]);
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Tiket berhasil diklasifikasi.')
        ->assertJsonPath('data.status', TicketStatus::Diklasifikasi->value)
        ->assertJsonPath('data.priority', TicketPriority::Critical->value)
        ->assertJsonPath('data.classified_by.id', $actor->id)
        ->assertJsonPath('data.tik_detail.quality_category.id', $qualityCategory->id)
        ->assertJsonPath('data.tik_detail.it_tag.id', $itTag->id);

    $ticket->refresh();

    expect($ticket->classified_at?->format('Y-m-d H:i:s'))->toBe('2026-09-24 08:00:00')
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-09-24 10:00:00');
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

    try {
        Carbon::setTestNow('2026-09-24 08:00:00');

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
            'sarpras_category_id' => $category->id,
            'priority' => TicketPriority::High->value,
        ]);
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Diklasifikasi->value)
        ->assertJsonPath('data.priority', TicketPriority::High->value)
        ->assertJsonPath('data.classified_by.id', $actor->id)
        ->assertJsonPath('data.sarpras_detail.sarpras_category.id', $category->id);

    expect($ticket->refresh()->sla_deadline?->format('Y-m-d H:i:s'))
        ->toBe('2026-09-24 12:00:00');
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
        'priority' => TicketPriority::Medium->value,
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
        'priority' => TicketPriority::Low->value,
    ])->assertForbidden();

    actingAsTicketWorkflowUser('tickets-verify', 'super-admin');
    $classifiedTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$classifiedTicket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $itTag->id,
        'priority' => TicketPriority::Low->value,
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

test('assignment cannot overwrite the priority selected during classification', function (): void {
    $actor = actingAsTicketWorkflowUser('tickets-assign');
    $officer = User::factory()->create([
        'name' => 'Petugas Terpilih',
        'status' => 'active',
    ]);
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Diklasifikasi,
        'priority' => TicketPriority::High,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'priority' => TicketPriority::Critical->value,
        'officer_id' => $officer->id,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Petugas berhasil ditugaskan.')
        ->assertJsonPath('data.priority', TicketPriority::High->value)
        ->assertJsonPath('data.assigned_officer.id', $officer->id)
        ->assertJsonPath('data.assigned_officer.name', 'Petugas Terpilih')
        ->assertJsonPath('data.status', TicketStatus::Ditugaskan->value);
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'priority' => TicketPriority::High->value,
        'assigned_officer_id' => $officer->id,
        'status' => TicketStatus::Ditugaskan->value,
    ]);
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Diklasifikasi->value,
        'to_status' => TicketStatus::Ditugaskan->value,
        'changed_by_id' => $actor->id,
    ]);
});

test('assign returns 422 when officer is missing', function (): void {
    actingAsTicketWorkflowUser('tickets-assign');
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Diklasifikasi,
        'priority' => TicketPriority::High,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign");

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('officer_id');
    expect($ticket->refresh()->priority)->toBe(TicketPriority::High)
        ->and($ticket->assigned_officer_id)->toBeNull();
});

test('assign returns 422 for an unavailable officer', function (int $officerId): void {
    actingAsTicketWorkflowUser('tickets-assign');
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Diklasifikasi,
        'priority' => TicketPriority::High,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'officer_id' => $officerId,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('officer_id');
    expect($ticket->refresh()->priority)->toBe(TicketPriority::High)
        ->and($ticket->assigned_officer_id)->toBeNull();
})->with([
    'unknown user' => 999999,
    'inactive user' => fn (): int => User::factory()->create(['status' => 'inactive'])->id,
    'suspended user' => fn (): int => User::factory()->create(['status' => 'suspended'])->id,
]);

test('assign returns 409 when ticket is not classified', function (TicketStatus $status): void {
    actingAsTicketWorkflowUser('tickets-assign');
    $officer = User::factory()->create(['status' => 'active']);
    $ticket = Ticket::factory()->create([
        'status' => $status,
        'priority' => TicketPriority::Low,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'officer_id' => $officer->id,
    ]);

    $response
        ->assertConflict()
        ->assertJsonPath('message', "Status tiket tidak dapat diubah dari {$status->value} menjadi ditugaskan.");
    expect($ticket->refresh()->status)->toBe($status)
        ->and($ticket->priority)->toBe(TicketPriority::Low)
        ->and($ticket->assigned_officer_id)->toBeNull();
})->with([
    'new' => TicketStatus::Baru,
    'rejected' => TicketStatus::Ditolak,
    'in progress' => TicketStatus::Diproses,
    'completed' => TicketStatus::Terselesaikan,
]);

test('guest cannot use ticket workflow endpoints', function (string $endpoint, array $payload): void {
    $ticket = Ticket::factory()->create();

    $this->postJson("/api/v1/tickets/{$ticket->id}/{$endpoint}", $payload)
        ->assertUnauthorized();
})->with([
    'classify' => ['classify', []],
    'reject' => ['reject', ['reason' => 'Tidak dapat diproses.']],
    'assign' => ['assign', ['officer_id' => 1]],
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
    'assign' => ['assign', ['officer_id' => 1]],
]);

test('ticket workflow endpoint returns 404 when ticket does not exist', function (string $endpoint, array $payload, string $permission): void {
    actingAsTicketWorkflowUser($permission);

    $this->postJson("/api/v1/tickets/999999/{$endpoint}", $payload)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
})->with([
    'classify' => ['classify', [], 'tickets-verify'],
    'reject' => ['reject', ['reason' => 'Tidak dapat diproses.'], 'tickets-reject'],
    'assign' => ['assign', ['officer_id' => 999999], 'tickets-assign'],
]);
