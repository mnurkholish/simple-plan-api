<?php

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsSlaFilterSuperAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $permission = Permission::firstOrCreate(['name' => 'tickets-access', 'guard_name' => 'web']);

    $user = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $user->assignRole($role);
    $user->givePermissionTo($permission);

    Sanctum::actingAs($user);

    return $user;
}

test('filter status_sla melewati_batas mengembalikan tiket yang terlambat atau belum selesai melebihi deadline', function (): void {
    actingAsSlaFilterSuperAdmin();

    Carbon::setTestNow('2026-10-15 12:00:00');

    try {
        // 1. Melewati batas: belum selesai dan deadline sudah lewat
        $lateInProgress = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Diproses,
            'priority' => TicketPriority::Medium,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-15 10:00:00',
            'completed_at' => null,
        ]);

        // 2. Melewati batas: selesai tetapi diselesaikan setelah deadline
        $lateCompleted = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Terselesaikan,
            'priority' => TicketPriority::High,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-15 10:00:00',
            'completed_at' => '2026-10-15 11:00:00',
        ]);

        // 3. Tepat waktu: belum selesai dan sisa waktu masih banyak
        $onTimeInProgress = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Diproses,
            'priority' => TicketPriority::Medium,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-15 18:00:00',
            'completed_at' => null,
        ]);

        $response = $this->getJson('/api/v1/tickets?status_sla=melewati_batas')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        expect($ids)->toContain($lateInProgress->id, $lateCompleted->id)
            ->not->toContain($onTimeInProgress->id);

        $firstLate = collect($response->json('data'))->firstWhere('id', $lateInProgress->id);
        expect($firstLate['status_sla'])->toBe('Melewati Batas');
    } finally {
        Carbon::setTestNow();
    }
});

test('filter status_sla tepat_waktu mengembalikan tiket yang diselesaikan tepat waktu atau masih aman', function (): void {
    actingAsSlaFilterSuperAdmin();

    Carbon::setTestNow('2026-10-15 12:00:00');

    try {
        // Tepat waktu: selesai sebelum deadline
        $onTimeCompleted = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Terselesaikan,
            'priority' => TicketPriority::Medium,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-15 16:00:00',
            'completed_at' => '2026-10-15 11:30:00',
        ]);

        // Tepat waktu: sedang diproses dan sisa waktu masih > 25% window
        // Window = 8 jam (08:00 s/d 16:00). 25% = 2 jam. Sekarang jam 12:00 -> sisa 4 jam (> 2 jam).
        $onTimeInProgress = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Diproses,
            'priority' => TicketPriority::Medium,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-15 16:00:00',
            'completed_at' => null,
        ]);

        // Melewati batas
        $lateTicket = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Diproses,
            'priority' => TicketPriority::High,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-15 10:00:00',
            'completed_at' => null,
        ]);

        $response = $this->getJson('/api/v1/tickets?status_sla=tepat_waktu')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        expect($ids)->toContain($onTimeCompleted->id, $onTimeInProgress->id)
            ->not->toContain($lateTicket->id);
    } finally {
        Carbon::setTestNow();
    }
});

test('filter status_sla mendekati_batas mengembalikan tiket belum selesai dengan sisa waktu <= 25 persen', function (): void {
    actingAsSlaFilterSuperAdmin();

    Carbon::setTestNow('2026-10-15 12:00:00');

    try {
        // Window = 4 jam (08:00 s/d 12:40 = 280 menit). 25% = 70 menit.
        // Sekarang jam 12:00 -> sisa 40 menit (<= 70 menit) -> mendekati_batas
        $nearDeadlineTicket = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Diproses,
            'priority' => TicketPriority::High,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-15 12:40:00',
            'completed_at' => null,
        ]);

        // Masih aman (sisa waktu masih > 25%)
        $safeTicket = Ticket::factory()->create([
            'service' => TicketService::Tik,
            'status' => TicketStatus::Diproses,
            'priority' => TicketPriority::Low,
            'sla_started_at' => '2026-10-15 08:00:00',
            'sla_deadline' => '2026-10-18 08:00:00',
            'completed_at' => null,
        ]);

        $response = $this->getJson('/api/v1/tickets?status_sla=mendekati_batas')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        expect($ids)->toContain($nearDeadlineTicket->id)
            ->not->toContain($safeTicket->id);

        $item = collect($response->json('data'))->firstWhere('id', $nearDeadlineTicket->id);
        expect($item['status_sla'])->toBe('Mendekati Batas');
    } finally {
        Carbon::setTestNow();
    }
});

test('parameter status_sla divalidasi hanya menerima nilai enum yang diizinkan', function (): void {
    actingAsSlaFilterSuperAdmin();

    $this->getJson('/api/v1/tickets?status_sla=tidak_valid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status_sla');
});
