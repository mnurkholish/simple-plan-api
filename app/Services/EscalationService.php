<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Exception;

class EscalationService
{
    /**
     * Mengekalasi tiket ke pihak ketiga atau terkait.
     *
     * @param Ticket $ticket
     * @param array $data
     * @param int $escalatedById
     * @return Ticket
     * @throws Exception
     */
    public function escalateTicket(Ticket $ticket, array $data, int $escalatedById): Ticket
    {
        // a. Pastikan status tiket saat ini adalah 'diproses'
        if ($ticket->status !== 'diproses') {
            throw new Exception("Hanya tiket berstatus 'diproses' yang dapat dieskalasi.");
        }

        return DB::transaction(function () use ($ticket, $data, $escalatedById) {
            // b. Insert data ke tabel ticket_escalations
            $ticket->escalations()->create([
                'escalated_by_id' => $escalatedById,
                'target'          => $data['target'],
                'notes'           => $data['notes'],
                'escalated_at'    => now(),
            ]);

            // d. Insert data ke ticket_status_histories
            // Harus dilakukan sebelum update karena mengambil status tiket saat ini ('diproses')
            $ticket->statusHistories()->create([
                'from_status'   => $ticket->status,
                'to_status'     => 'eskalasi',
                'changed_by_id' => $escalatedById,
                'notes'         => $data['notes'],
            ]);

            // c. Update status tickets menjadi 'eskalasi'
            $ticket->update([
                'status' => 'eskalasi',
            ]);

            return $ticket;
        });
    }

    /**
     * Melanjutkan penanganan tiket setelah selesai dari eskalasi.
     *
     * @param Ticket $ticket
     * @param int $resumedById
     * @return Ticket
     * @throws Exception
     */
    public function resumeTicket(Ticket $ticket, int $resumedById): Ticket
    {
        // a. Pastikan status tiket saat ini adalah 'eskalasi'
        if ($ticket->status !== 'eskalasi') {
            throw new Exception("Hanya tiket berstatus 'eskalasi' yang penanganannya dapat dilanjutkan.");
        }

        return DB::transaction(function () use ($ticket, $resumedById) {
            // c. Insert data ke ticket_status_histories
            $ticket->statusHistories()->create([
                'from_status'   => $ticket->status, // 'eskalasi'
                'to_status'     => 'diproses',
                'changed_by_id' => $resumedById,
                'notes'         => 'Penanganan dilanjutkan pasca eskalasi',
            ]);

            // b. Update status tickets kembali menjadi 'diproses'
            $ticket->update([
                'status' => 'diproses',
            ]);

            return $ticket;
        });
    }
}
