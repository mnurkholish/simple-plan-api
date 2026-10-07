<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EscalationService
{
    /**
     * Mengekalasi tiket ke pihak ketiga atau terkait.
     *
     * @throws ConflictHttpException
     */
    public function escalateTicket(Ticket $ticket, array $data, int $escalatedById): Ticket
    {
        if ($ticket->status !== TicketStatus::Diproses) {
            throw new ConflictHttpException("Hanya tiket berstatus 'diproses' yang dapat dieskalasi.");
        }

        return DB::transaction(function () use ($ticket, $data, $escalatedById) {
            $ticket->escalations()->create([
                'escalated_by_id' => $escalatedById,
                'target' => $data['target'],
                'notes' => $data['notes'],
                'escalated_at' => now(),
            ]);

            $ticket->statusHistories()->create([
                'from_status' => $ticket->status,
                'to_status' => 'eskalasi',
                'changed_by_id' => $escalatedById,
                'notes' => $data['notes'],
            ]);

            $ticket->update([
                'status' => 'eskalasi',
            ]);

            return $ticket;
        });
    }

    /**
     * Melanjutkan penanganan tiket setelah selesai dari eskalasi.
     *
     * @throws ConflictHttpException
     */
    public function resumeTicket(Ticket $ticket, int $resumedById): Ticket
    {
        if ($ticket->status !== TicketStatus::Eskalasi) {
            throw new ConflictHttpException("Hanya tiket berstatus 'eskalasi' yang penanganannya dapat dilanjutkan.");
        }

        return DB::transaction(function () use ($ticket, $resumedById) {
            $ticket->statusHistories()->create([
                'from_status' => $ticket->status,
                'to_status' => 'diproses',
                'changed_by_id' => $resumedById,
                'notes' => 'Penanganan dilanjutkan pasca eskalasi',
            ]);

            $ticket->update([
                'status' => 'diproses',
            ]);

            return $ticket;
        });
    }
}
