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

function actingAsTicketRejectUser(?string $roleName = null): User
{
    $user = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $permission = Permission::firstOrCreate([
        'name' => 'tickets-reject',
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

test('classification-options mengembalikan opsi aktif sesuai service yang diminta', function (): void {
    actingAsTicketClassificationUser('super-admin');

    $activeQuality = QualityCategory::create(['name' => 'Kerusakan Jaringan', 'is_active' => true]);
    $inactiveQuality = QualityCategory::create(['name' => 'Kategori Nonaktif', 'is_active' => false]);
    $activeTag = ItTag::create(['name' => 'Switch / Router', 'is_active' => true]);
    $inactiveTag = ItTag::create(['name' => 'Tag Nonaktif', 'is_active' => false]);
    $sarprasCat = SarprasCategory::create(['name' => 'Alkes', 'is_active' => true]);

    // Service TIK
    $responseTik = $this->getJson('/api/v1/tickets/classification-options?service=tik')
        ->assertOk()
        ->assertJsonPath('data.service', 'tik');

    $qualityIds = collect($responseTik->json('data.quality_categories'))->pluck('id')->all();
    $tagIds = collect($responseTik->json('data.it_tags'))->pluck('id')->all();

    expect($qualityIds)->toContain($activeQuality->id)->not->toContain($inactiveQuality->id)
        ->and($tagIds)->toContain($activeTag->id)->not->toContain($inactiveTag->id)
        ->and($responseTik->json('data.sarpras_categories'))->toBeEmpty();

    // Service Sarpras
    $responseSarpras = $this->getJson('/api/v1/tickets/classification-options?service=sarpras')
        ->assertOk()
        ->assertJsonPath('data.service', 'sarpras')
        ->assertJsonPath('data.quality_categories', [])
        ->assertJsonPath('data.it_tags', []);

    $sarprasIds = collect($responseSarpras->json('data.sarpras_categories'))->pluck('id')->all();
    expect($sarprasIds)->toContain($sarprasCat->id);
});

test('classification-options memvalidasi parameter service', function (): void {
    actingAsTicketClassificationUser('super-admin');

    $this->getJson('/api/v1/tickets/classification-options')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service');

    $this->getJson('/api/v1/tickets/classification-options?service=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service');
});

test('guest atau user tanpa permission tickets-verify tidak dapat mengakses classification-options', function (): void {
    $this->getJson('/api/v1/tickets/classification-options?service=tik')
        ->assertUnauthorized();

    $user = createTicketClassificationReporter();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/tickets/classification-options?service=tik')
        ->assertForbidden();
});

test('super admin dapat menolak tiket TIK dan Sarpras yang berstatus baru', function (): void {
    $actor = actingAsTicketRejectUser('super-admin');
    $tikTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);
    $sarprasTicket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$tikTicket->id}/reject", [
        'reason' => 'Bukan wewenang unit IT.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Ditolak->value)
        ->assertJsonPath('message', 'Tiket berhasil ditolak.');

    $this->postJson("/api/v1/tickets/{$sarprasTicket->id}/reject", [
        'reason' => 'Perlu surat pengajuan resmi.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Ditolak->value);

    expect($tikTicket->refresh()->status)->toBe(TicketStatus::Ditolak)
        ->and($sarprasTicket->refresh()->status)->toBe(TicketStatus::Ditolak);

    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $tikTicket->id,
        'from_status' => TicketStatus::Baru->value,
        'to_status' => TicketStatus::Ditolak->value,
        'changed_by_id' => $actor->id,
        'notes' => 'Bukan wewenang unit IT.',
    ]);
});

test('koordinator Sarpras dapat menolak tiket Sarpras tetapi tidak dapat menolak tiket TIK', function (): void {
    actingAsTicketRejectUser('koordinator-sarpras');

    $sarprasTicket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Baru,
    ]);
    $tikTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$sarprasTicket->id}/reject", [
        'reason' => 'Barang sudah tidak digaransi.',
    ])->assertOk();

    $this->postJson("/api/v1/tickets/{$tikTicket->id}/reject", [
        'reason' => 'Menolak tiket TIK.',
    ])->assertForbidden();

    expect($sarprasTicket->refresh()->status)->toBe(TicketStatus::Ditolak)
        ->and($tikTicket->refresh()->status)->toBe(TicketStatus::Baru);
});

test('penolakan tiket wajib menyertakan reason', function (): void {
    actingAsTicketRejectUser('super-admin');

    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reject", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Baru);
});

test('tiket yang tidak berstatus baru tidak dapat ditolak', function (): void {
    actingAsTicketRejectUser('super-admin');

    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reject", [
        'reason' => 'Sudah terlambat ditolak.',
    ])
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'Status tiket tidak dapat diubah dari diklasifikasi menjadi ditolak.',
        );

    expect($ticket->refresh()->status)->toBe(TicketStatus::Diklasifikasi);
});

test('user tanpa permission tickets-reject atau role tidak berwenang tidak dapat menolak tiket', function (): void {
    $reporter = createTicketClassificationReporter();
    $ticket = Ticket::factory()->reportedBy($reporter)->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Baru,
    ]);

    // Guest
    $this->postJson("/api/v1/tickets/{$ticket->id}/reject", ['reason' => 'Ditolak'])
        ->assertUnauthorized();

    // User without permission
    Sanctum::actingAs($reporter);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reject", ['reason' => 'Ditolak'])
        ->assertForbidden();
});
