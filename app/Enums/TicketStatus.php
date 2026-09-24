<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Baru = 'baru';
    case Diklasifikasi = 'diklasifikasi';
    case Ditugaskan = 'ditugaskan';
    case Diproses = 'diproses';
    case Eskalasi = 'eskalasi';
    case Terselesaikan = 'terselesaikan';
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
            self::Diklasifikasi => [self::Ditugaskan],
            self::Ditugaskan => [self::Diproses],
            self::Diproses => [self::Ditugaskan, self::Eskalasi, self::Terselesaikan],
            self::Eskalasi => [self::Diproses],
            self::Terselesaikan => [self::Terverifikasi, self::Diproses],
            self::Terverifikasi => [self::Ditutup],
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
