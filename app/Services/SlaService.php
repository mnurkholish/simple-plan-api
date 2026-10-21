<?php

namespace App\Services;

use App\Enums\TicketPriority;
use Carbon\Carbon;

class SlaService
{
    public function calculateDeadline(Carbon $slaStartedAt, TicketPriority $priority): Carbon
    {
        $deadline = $slaStartedAt->copy();

        return match ($priority) {
            TicketPriority::Critical => $deadline->addHours(2),
            TicketPriority::High => $deadline->addHours(4),
            TicketPriority::Medium => $deadline->addDay(),
            TicketPriority::Low => $deadline->addDays(3),
        };
    }

    public function determineSlaStatus(Carbon $slaStartedAt, Carbon $deadline, ?Carbon $completedAt = null): string
    {
        if ($completedAt !== null) {
            if ($completedAt->greaterThan($deadline)) {
                return 'Melewati Batas';
            }

            return 'Tepat Waktu';
        }

        $now = now();

        if ($now->greaterThan($deadline)) {
            return 'Melewati Batas';
        }

        $totalMinutes = $slaStartedAt->diffInMinutes($deadline);
        $remainingMinutes = $now->diffInMinutes($deadline, false);

        if ($totalMinutes > 0 && $remainingMinutes <= ($totalMinutes * 0.25)) {
            return 'Mendekati Batas';
        }

        return 'Tepat Waktu';
    }
}
