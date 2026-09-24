<?php

namespace App\Services;

use App\Enums\TicketPriority;
use Carbon\Carbon;

class SlaService
{
    /**
     * Menghitung deadline berdasarkan waktu assignment pertama dan priority tiket.
     *
     * @return Carbon Waktu deadline SLA
     */
    public function calculateDeadline(Carbon $assignedAt, TicketPriority $priority): Carbon
    {
        $deadline = $assignedAt->copy();

        return match ($priority) {
            TicketPriority::Critical => $deadline->addHours(2),
            TicketPriority::High => $deadline->addHours(4),
            TicketPriority::Medium => $deadline->addDay(),
            TicketPriority::Low => $deadline->addDays(3),
        };
    }

    /**
     * Menentukan status SLA secara dinamis.
     *
     * Catatan: Parameter $assignedAt digunakan untuk menghitung total durasi SLA
     * (selisih antara assignedAt dan deadline) agar bisa mendapatkan nilai 25% nya.
     *
     * @param  Carbon  $assignedAt  Waktu assignment pertama (untuk hitung total durasi)
     * @param  Carbon  $deadline  Waktu deadline tiket
     * @param  Carbon|null  $completedAt  Waktu tiket selesai (opsional)
     * @return string Status SLA ('Melewati Batas', 'Mendekati Batas', 'Tepat Waktu')
     */
    public function determineSlaStatus(Carbon $assignedAt, Carbon $deadline, ?Carbon $completedAt = null): string
    {
        // 1. Jika tiket sudah selesai
        if ($completedAt !== null) {
            if ($completedAt->greaterThan($deadline)) {
                return 'Melewati Batas';
            }

            return 'Tepat Waktu';
        }

        // 2. Jika tiket belum selesai
        $now = now();

        if ($now->greaterThan($deadline)) {
            return 'Melewati Batas';
        }

        // Hitung sisa waktu dan total waktu
        $totalMinutes = $assignedAt->diffInMinutes($deadline);
        $remainingMinutes = $now->diffInMinutes($deadline, false); // false = jangan absolut, jika minus tetap minus

        // Jika sisa waktu <= 25% dari total waktu SLA awal
        if ($totalMinutes > 0 && $remainingMinutes <= ($totalMinutes * 0.25)) {
            return 'Mendekati Batas';
        }

        return 'Tepat Waktu';
    }
}
