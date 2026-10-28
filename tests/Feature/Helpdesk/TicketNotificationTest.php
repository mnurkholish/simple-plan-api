<?php

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\TicketStatusUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createNotificationTestUser(string $roleName, ?Unit $unit = null): User
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    $user = User::factory()->create([
        'unit_id' => $unit?->id ?? Unit::factory(),
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $user->assignRole($role);

    return $user;
}

test('notifikasi dikirim ke Super Admin dan Management saat tiket dieskalasi', function (): void {
    Notification::fake();

    $superAdmin = createNotificationTestUser('super-admin');
    $management = createNotificationTestUser('management');
    $officer = createNotificationTestUser('petugas-tik');

    $permission = Permission::firstOrCreate(['name' => 'tickets-handle', 'guard_name' => 'web']);
    $officer->givePermissionTo($permission);
    Sanctum::actingAs($officer);

    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $officer->id,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/escalate", [
        'target' => 'Vendor',
        'notes' => 'Kerusakan hardware memerlukan penggantian suku cadang oleh vendor.',
    ]);

    $response->assertOk();

    Notification::assertSentTo(
        [$superAdmin, $management],
        TicketStatusUpdatedNotification::class,
        function (TicketStatusUpdatedNotification $notification) use ($ticket): bool {
            $data = $notification->toArray(new User);

            return $notification->ticket->id === $ticket->id
                && $data['ticket_id'] === $ticket->id
                && $data['nomor_tiket'] === $ticket->ticket_number
                && $data['status'] === TicketStatus::Eskalasi->value
                && str_contains($data['message'], 'Vendor')
                && $data['title'] === 'Update Status Tiket'
                && $data['icon'] === 'IconTicket'
                && $data['color'] === 'blue';
        }
    );
});

test('notifikasi dikirim ke pelapor saat pekerjaan teknis tiket telah terselesaikan', function (): void {
    Notification::fake();

    $reporter = createNotificationTestUser('user');
    $officer = createNotificationTestUser('petugas-tik');

    $permission = Permission::firstOrCreate(['name' => 'tickets-handle', 'guard_name' => 'web']);
    $officer->givePermissionTo($permission);
    Sanctum::actingAs($officer);

    $ticket = Ticket::factory()->reportedBy($reporter)->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $officer->id,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/handlings", [
        'notes' => 'Kabel LAN dan konfigurasi IP selesai diperbaiki.',
        'status' => TicketStatus::Terselesaikan->value,
        'started_at' => '2026-10-10 09:00:00',
        'completed_at' => '2026-10-10 10:00:00',
    ]);

    $response->assertOk();

    Notification::assertSentTo(
        $reporter,
        TicketStatusUpdatedNotification::class,
        function (TicketStatusUpdatedNotification $notification) use ($ticket): bool {
            $data = $notification->toArray(new User);

            return $data['ticket_id'] === $ticket->id
                && $data['status'] === TicketStatus::Terselesaikan->value
                && str_contains($data['message'], 'menunggu verifikasi');
        }
    );
});

test('notifikasi dikirim ke teknisi saat verifikasi penyelesaian disetujui (tiket ditutup)', function (): void {
    Notification::fake();

    $reporter = createNotificationTestUser('user');
    $officer = createNotificationTestUser('petugas-tik');

    $ticket = Ticket::factory()->reportedBy($reporter)->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Terselesaikan,
        'assigned_officer_id' => $officer->id,
    ]);

    Sanctum::actingAs($reporter);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
        'is_approved' => true,
    ]);

    $response->assertOk();

    Notification::assertSentTo(
        $officer,
        TicketStatusUpdatedNotification::class,
        function (TicketStatusUpdatedNotification $notification) use ($ticket): bool {
            $data = $notification->toArray(new User);

            return $data['ticket_id'] === $ticket->id
                && $data['status'] === TicketStatus::Ditutup->value
                && str_contains($data['message'], 'telah disetujui (Ditutup)');
        }
    );
});

test('notifikasi dikirim ke teknisi saat verifikasi penyelesaian ditolak oleh pelapor', function (): void {
    Notification::fake();

    $reporter = createNotificationTestUser('user');
    $officer = createNotificationTestUser('petugas-tik');

    $ticket = Ticket::factory()->reportedBy($reporter)->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Terselesaikan,
        'assigned_officer_id' => $officer->id,
    ]);

    Sanctum::actingAs($reporter);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
        'is_approved' => false,
        'keterangan_kendala' => 'Jaringan komputer masih putus nyambung.',
    ]);

    $response->assertOk();

    Notification::assertSentTo(
        $officer,
        TicketStatusUpdatedNotification::class,
        function (TicketStatusUpdatedNotification $notification) use ($ticket): bool {
            $data = $notification->toArray(new User);

            return $data['ticket_id'] === $ticket->id
                && $data['status'] === TicketStatus::Diproses->value
                && str_contains($data['message'], 'ditolak/dikembalikan dengan kendala');
        }
    );
});
