<?php

namespace App\Services;

use Carbon\Carbon;

class SlaService
{
    /**
     * Menghitung deadline berdasarkan waktu klasifikasi dan priority tiket.
     *
     * @param Carbon $classifiedAt Waktu ketika tiket diklasifikasi
     * @param string $priority     Prioritas tiket ('critical', 'high', 'medium', 'low')
     * @return Carbon Waktu deadline SLA
     */
    public function calculateDeadline(Carbon $classifiedAt, string $priority): Carbon
    {
        $deadline = $classifiedAt->copy();

        switch (strtolower($priority)) {
            case 'critical':
                return $deadline->addHours(2);
            case 'high':
                return $deadline->addHours(4);
            case 'medium':
                return $deadline->addHours(24); // 1 hari
            case 'low':
                return $deadline->addHours(72); // 3 hari
            default:
                return $deadline->addHours(24); // Default ke medium jika tidak valid
        }
    }

    /**
     * Menentukan status SLA secara dinamis.
     * 
     * Catatan: Parameter $classifiedAt ditambahkan ke dalam signature untuk menghitung 
     * total durasi SLA (selisih antara classifiedAt dan deadline) agar bisa mendapatkan nilai 25% nya.
     *
     * @param Carbon $classifiedAt Waktu tiket diklasifikasi (untuk hitung total durasi)
     * @param Carbon $deadline     Waktu deadline tiket
     * @param Carbon|null $completedAt Waktu tiket selesai (opsional)
     * @return string Status SLA ('Melewati Batas', 'Mendekati Batas', 'Tepat Waktu')
     */
    public function determineSlaStatus(Carbon $classifiedAt, Carbon $deadline, ?Carbon $completedAt = null): string
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
        $totalMinutes = $classifiedAt->diffInMinutes($deadline);
        $remainingMinutes = $now->diffInMinutes($deadline, false); // false = jangan absolut, jika minus tetap minus

        // Jika sisa waktu <= 25% dari total waktu SLA awal
        if ($totalMinutes > 0 && $remainingMinutes <= ($totalMinutes * 0.25)) {
            return 'Mendekati Batas';
        }

        return 'Tepat Waktu';
    }
}
