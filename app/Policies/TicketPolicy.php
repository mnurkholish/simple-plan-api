<?php

namespace App\Policies;

use App\Enums\TicketService;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets-access');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user)
            && Ticket::query()
                ->visibleTo($user)
                ->whereKey($ticket->getKey())
                ->exists();
    }

    public function classify(User $user, Ticket $ticket): bool
    {
        return $this->canClassifyService($user, $ticket->service);
    }

    public function reject(User $user, Ticket $ticket): bool
    {
        return $this->canClassifyService($user, $ticket->service);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return match ($ticket->service) {
            TicketService::Tik => $user->hasRole('super-admin'),
            TicketService::Sarpras => $user->hasRole('koordinator-sarpras'),
        };
    }

    public function handle(User $user, Ticket $ticket): bool
    {
        if ((int) $ticket->assigned_officer_id !== (int) $user->getKey()) {
            return false;
        }

        return match ($ticket->service) {
            TicketService::Tik => $user->hasRole('petugas-tik'),
            TicketService::Sarpras => $user->hasRole('petugas-sarpras'),
        };
    }

    private function canClassifyService(User $user, TicketService $service): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $service === TicketService::Sarpras
            && $user->hasRole('koordinator-sarpras');
    }
}
