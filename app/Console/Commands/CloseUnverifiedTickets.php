<?php

namespace App\Console\Commands;

use App\Services\TicketService;
use Illuminate\Console\Command;

class CloseUnverifiedTickets extends Command
{
    protected $signature = 'tickets:auto-close-unverified';

    protected $description = 'Close completed tickets that have not been verified by the reporter within two days';

    public function handle(TicketService $tickets): int
    {
        $closedCount = $tickets->autoCloseUnverifiedResolutions();

        $this->info("{$closedCount} tiket ditutup otomatis.");

        return self::SUCCESS;
    }
}
