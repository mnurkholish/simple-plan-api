<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Baru = 'baru';
    case Diklasifikasi = 'diklasifikasi';
    // Legacy value retained only so historical records remain readable.
    case Ditugaskan = 'ditugaskan';
    case Diproses = 'diproses';
    // Reserved legacy value; escalation is not part of the active handling flow.
    case Eskalasi = 'eskalasi';
    case Terselesaikan = 'terselesaikan';
    // Legacy value retained only so historical records remain readable.
    case Terverifikasi = 'terverifikasi';
    case Ditutup = 'ditutup';
    case Ditolak = 'ditolak';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Baru => [self::Diklasifikasi, self::Ditolak],
            self::Diklasifikasi => [self::Diproses],
            self::Ditugaskan => [],
            self::Diproses => [self::Terselesaikan],
            self::Eskalasi => [],
            self::Terselesaikan => [self::Ditutup],
            self::Terverifikasi => [],
            self::Ditutup, self::Ditolak => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Ditutup || $this === self::Ditolak;
    }
}
