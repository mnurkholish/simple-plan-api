<?php

namespace App\Services;

use App\Models\User;
use App\Models\Ticket;
use App\Enums\TicketService;
use App\Notifications\SystemActivityNotification;
use App\Notifications\TicketStatusUpdatedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, int $perPage = 15): array
    {
        $notifications = $user->notifications()
            ->latest()
            ->paginate($perPage)
            ->through(fn (DatabaseNotification $notification): array => $this->format($notification));

        return [
            ...$notifications->toArray(),
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $user->notifications()
            ->whereKey($notificationId)
            ->whereNull('read_at')
            ->firstOrFail()
            ->markAsRead();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function recordLogin(User $user): void
    {
        $this->notify($user, 'Login berhasil', 'Ada sesi login baru pada '.now()->format('d M Y H:i').'.', 'IconLogin', 'emerald');
    }

    public function recordLogout(User $user): void
    {
        $this->notify($user, 'Logout berhasil', 'Sesi Anda berakhir pada '.now()->format('d M Y H:i').'.', 'IconLogout', 'slate');
    }

    public function ticketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing(['reporter:id,name', 'unit:id,unit_name']);
        $roles = $ticket->service === TicketService::Tik
            ? ['super-admin']
            : ['super-admin', 'koordinator-sarpras'];
        $title = $ticket->service === TicketService::Tik ? 'Tiket TIK Baru Masuk' : 'Tiket Sarpras Baru Masuk';
        $message = "Tiket {$ticket->ticket_number} dari {$ticket->reporter->name} ({$ticket->unit->unit_name}) telah masuk. Klik untuk melihat detail dan mengklasifikasikan tiket.";

        foreach (User::role($roles)->get()->unique('id') as $user) {
            $this->notifyTicket($user, $ticket, $title, $message);
        }
    }

    public function ticketRejected(Ticket $ticket): void
    {
        $this->notifyTicket($ticket->reporter, $ticket, 'Tiket Ditolak',
            "Tiket {$ticket->ticket_number} Anda ditolak. Klik untuk melihat alasan penolakan pada detail tiket.");
    }

    public function ticketAssigned(Ticket $ticket): void
    {
        $this->notifyTicket($ticket->assignedOfficer, $ticket, 'Tiket Baru Ditugaskan',
            "Anda ditugaskan menangani tiket {$ticket->ticket_number} dengan prioritas {$ticket->priority->value}. Klik untuk melihat detail dan mulai penanganan.");
    }

    public function ticketEscalated(Ticket $ticket, User $officer): void
    {
        $isTik = $ticket->service === TicketService::Tik;
        $title = $isTik ? 'Tiket TIK Dieskalasi' : 'Tiket Sarpras Dieskalasi';
        $message = "Tiket {$ticket->ticket_number} dieskalasi oleh {$officer->name}. Klik untuk melihat detail tiket.";

        foreach (User::role($isTik ? 'super-admin' : 'koordinator-sarpras')->get()->unique('id') as $user) {
            $this->notifyTicket($user, $ticket, $title, $message);
        }
    }

    public function ticketResolved(Ticket $ticket): void
    {
        $this->notifyTicket($ticket->reporter, $ticket, 'Tiket Selesai Ditangani, Mohon Verifikasi',
            "Tiket {$ticket->ticket_number} Anda telah selesai ditangani. Klik untuk memeriksa hasil perbaikan dan memverifikasi penyelesaian.");
    }

    public function ticketVerified(Ticket $ticket, bool $approved): void
    {
        if ($ticket->assignedOfficer === null) {
            return;
        }

        $title = $approved ? 'Penyelesaian Tiket Diverifikasi' : 'Penyelesaian Tiket Ditolak';
        $message = $approved
            ? "Pelapor telah memverifikasi penyelesaian tiket {$ticket->ticket_number}. Tiket kini berstatus Ditutup."
            : "Pelapor menolak penyelesaian tiket {$ticket->ticket_number}. Tiket kembali berstatus Diproses. Klik untuk melihat detail dan melanjutkan penanganan.";
        $this->notifyTicket($ticket->assignedOfficer, $ticket, $title, $message);
    }

    private function notifyTicket(User $user, Ticket $ticket, string $title, string $message): void
    {
        $user->notify(new TicketStatusUpdatedNotification($ticket, $message, $title));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activitySummary(): array
    {
        $today = now()->format('Y-m-d');

        return [
            [
                'id' => 'activity-login-today',
                'title' => 'Login hari ini',
                'subtitle' => Cache::get('login_today_'.$today, 0).' sesi login tercatat.',
            ],
            [
                'id' => 'activity-logout-today',
                'title' => 'Logout hari ini',
                'subtitle' => Cache::get('logout_today_'.$today, 0).' sesi logout tercatat.',
            ],
        ];
    }

    private function notify(
        User $user,
        string $title,
        string $subtitle,
        string $icon,
        string $color,
    ): void {
        $user->notify(new SystemActivityNotification($title, $subtitle, $icon, $color));
    }

    /**
     * @return array<string, mixed>
     */
    private function format(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Notifikasi',
            'subtitle' => $notification->data['subtitle'] ?? '',
            'ticket_id' => $notification->data['ticket_id'] ?? null,
            'nomor_tiket' => $notification->data['nomor_tiket'] ?? null,
            'url' => $notification->data['url'] ?? null,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
        ];
    }
}
