<?php

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

function createTicketClassificationReporter(): User
{
    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $reporter = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $reporter->assignRole($role);

    return $reporter;
}

function actingAsTicketClassificationUser(?string $roleName = null): User
{
    $user = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $permission = Permission::firstOrCreate([
        'name' => 'tickets-verify',
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

test('super admin dapat mengklasifikasi tiket TIK dan Sarpras yang berstatus baru', function (): void {
    $actor = actingAsTicketClassificationUser('super-admin');
    $qualityCategory = QualityCategory::create(['name' => 'Ketidakstabilan System', 'is_active' => true]);
    $itTag = ItTag::create(['name' => 'Server', 'is_active' => true]);
    $sarprasCategory = SarprasCategory::create(['name' => 'Alkes', 'is_active' => true]);
    $tikTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);
    $sarprasTicket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$tikTicket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $itTag->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Diklasifikasi->value)
        ->assertJsonPath('data.priority', null)
        ->assertJsonPath('data.sla_deadline', null)
        ->assertJsonPath('data.classified_by.id', $actor->id)
        ->assertJsonPath('data.tik_detail.quality_category.id', $qualityCategory->id)
        ->assertJsonPath('data.tik_detail.it_tag.id', $itTag->id)
        ->assertJsonPath('data.tik_detail.custom_it_tag_text', null);

    $this->postJson("/api/v1/tickets/{$sarprasTicket->id}/classify", [
        'sarpras_category_id' => $sarprasCategory->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Diklasifikasi->value)
        ->assertJsonPath('data.classified_by.id', $actor->id)
        ->assertJsonPath('data.sarpras_detail.sarpras_category.id', $sarprasCategory->id);

    expect($tikTicket->refresh()->status)->toBe(TicketStatus::Diklasifikasi)
        ->and($tikTicket->classified_by_id)->toBe($actor->id)
        ->and($tikTicket->classified_at)->not->toBeNull()
        ->and($tikTicket->priority)->toBeNull()
        ->and($tikTicket->sla_deadline)->toBeNull();
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $tikTicket->id,
        'from_status' => TicketStatus::Baru->value,
        'to_status' => TicketStatus::Diklasifikasi->value,
        'changed_by_id' => $actor->id,
    ]);
});

test('koordinator Sarpras dapat mengklasifikasi tiket Sarpras yang berstatus baru', function (): void {
    $actor = actingAsTicketClassificationUser('koordinator-sarpras');
    $category = SarprasCategory::create(['name' => 'Alkes', 'is_active' => true]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'sarpras_category_id' => $category->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.classified_by.id', $actor->id)
        ->assertJsonPath('data.sarpras_detail.sarpras_category.id', $category->id);
});

test('koordinator Sarpras tidak dapat mengklasifikasi tiket TIK', function (): void {
    actingAsTicketClassificationUser('koordinator-sarpras');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify")
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Baru)
        ->and($ticket->tikDetail)->toBeNull();
});

test('klasifikasi tiket TIK wajib menerima quality_category_id dan it_tag_id', function (): void {
    actingAsTicketClassificationUser('super-admin');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quality_category_id', 'it_tag_id']);
});

test('custom_it_tag_text hanya dapat digunakan ketika it_tag bernilai Lain-lain', function (): void {
    actingAsTicketClassificationUser('super-admin');
    $qualityCategory = QualityCategory::create(['name' => 'Ketidaksesuaian Program', 'is_active' => true]);
    $itTag = ItTag::create(['name' => 'Server', 'is_active' => true]);
    $otherTag = ItTag::create(['name' => 'Lain-lain', 'is_active' => true]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $itTag->id,
        'custom_it_tag_text' => 'Perangkat khusus',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('custom_it_tag_text');

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $otherTag->id,
        'custom_it_tag_text' => 'Perangkat khusus',
    ])
        ->assertOk()
        ->assertJsonPath('data.tik_detail.custom_it_tag_text', 'Perangkat khusus');
});

test('klasifikasi tiket Sarpras hanya menerima sarpras_category_id sebagai data klasifikasi', function (): void {
    actingAsTicketClassificationUser('super-admin');
    $sarprasCategory = SarprasCategory::create(['name' => 'Elektronik', 'is_active' => true]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'quality_category_id' => 999999,
        'it_tag_id' => 999999,
        'sarpras_category_id' => $sarprasCategory->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quality_category_id', 'it_tag_id']);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'sarpras_category_id' => $sarprasCategory->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.sarpras_detail.sarpras_category.id', $sarprasCategory->id)
        ->assertJsonPath('data.tik_detail', null);
});

test('tiket yang tidak berstatus baru tidak dapat diklasifikasi', function (): void {
    actingAsTicketClassificationUser('super-admin');
    $qualityCategory = QualityCategory::create(['name' => 'Ketidakstabilan System', 'is_active' => true]);
    $itTag = ItTag::create(['name' => 'Server', 'is_active' => true]);
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify", [
        'quality_category_id' => $qualityCategory->id,
        'it_tag_id' => $itTag->id,
    ])
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'Status tiket tidak dapat diubah dari diklasifikasi menjadi diklasifikasi.',
        );

    expect($ticket->refresh()->status)->toBe(TicketStatus::Diklasifikasi)
        ->and($ticket->tikDetail)->toBeNull();
});

test('guest tidak dapat mengklasifikasi tiket', function (): void {
    createTicketClassificationReporter();
    $ticket = Ticket::factory()->create();

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify")
        ->assertUnauthorized();
});

test('user terautentikasi tanpa permission klasifikasi tidak dapat mengklasifikasi tiket', function (): void {
    Permission::firstOrCreate([
        'name' => 'tickets-verify',
        'guard_name' => 'web',
    ]);
    $user = createTicketClassificationReporter();
    $ticket = Ticket::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/tickets/{$ticket->id}/classify")
        ->assertForbidden();
});

test('klasifikasi tiket yang tidak ditemukan mengembalikan 404', function (): void {
    actingAsTicketClassificationUser();

    $this->postJson('/api/v1/tickets/999999/classify')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
