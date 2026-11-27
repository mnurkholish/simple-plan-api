<?php

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\TicketNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('alur helpdesk memberi tahu admin, pelapor, dan petugas sesuai peristiwa', function (): void {
    $reporter = User::factory()->for(Unit::factory())->create();
    $officer = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    $ticket = Ticket::factory()->create([
        'reporter_id' => $reporter->id,
        'unit_id' => $reporter->unit_id,
        'assigned_officer_id' => $officer->id,
        'service' => TicketService::Tik,
        'priority' => TicketPriority::Medium,
    ]);
    $service = app(TicketNotificationService::class);

    $service->ticketCreated($ticket);
    $service->ticketEscalated($ticket, $officer);
    $service->ticketRejected($ticket);
    $service->ticketResolved($ticket);
    $service->ticketAssigned($ticket);
    $service->ticketVerified($ticket, false);
    $service->ticketVerified($ticket, true);

    expect($admin->notifications()->get()->pluck('data')->pluck('title')->all())
        ->toContain('Tiket TIK Baru Masuk', 'Tiket TIK Dieskalasi');
    expect($reporter->notifications()->get()->pluck('data')->pluck('title')->all())
        ->toContain('Tiket Ditolak', 'Tiket Selesai Ditangani, Mohon Verifikasi');
    expect($officer->notifications()->get()->pluck('data')->pluck('title')->all())
        ->toContain('Tiket Baru Ditugaskan', 'Penyelesaian Tiket Ditolak', 'Penyelesaian Tiket Diverifikasi');

    $data = $reporter->notifications()->firstOrFail()->data;
    expect($data['ticket_id'])->toBe($ticket->id)
        ->and($data['target']['feature'])->toBe('helpdesk');
});
