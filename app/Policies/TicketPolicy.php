<?php

namespace App\Policies;

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
}
