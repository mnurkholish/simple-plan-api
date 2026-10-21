<?php

namespace App\Services;

use App\Enums\TicketService;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusUpdatedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TicketNotificationService
{
    public function ticketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing(['reporter:id,name', 'unit:id,unit_name']);
        $roles = $ticket->service === TicketService::Tik
            ? ['super-admin']
            : ['super-admin', 'koordinator-sarpras'];
        $title = $ticket->service === TicketService::Tik ? 'Tiket TIK Baru Masuk' : 'Tiket Sarpras Baru Masuk';
        $message = "Tiket {$ticket->ticket_number} dari {$ticket->reporter->name} ({$ticket->unit->unit_name}) telah masuk. Klik untuk melihat detail dan mengklasifikasikan tiket.";

        foreach ($this->usersWithRoles($roles) as $user) {
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

        foreach ($this->usersWithRoles($isTik ? ['super-admin'] : ['koordinator-sarpras']) as $user) {
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

    /**
     * @param  array<int, string>  $roles
     * @return Collection<int, User>
     */
    private function usersWithRoles(array $roles): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->whereIn('name', $roles))
            ->get();
    }

    private function notifyTicket(User $user, Ticket $ticket, string $title, string $message): void
    {
        $user->notify(new TicketStatusUpdatedNotification($ticket, $message, $title));
    }
}
