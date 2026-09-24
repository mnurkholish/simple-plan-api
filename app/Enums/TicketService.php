<?php

namespace App\Enums;

enum TicketService: string
{
    case Tik = 'tik';
    case Sarpras = 'sarpras';

    public function ticketNumberPrefix(): string
    {
        return match ($this) {
            self::Tik => 'TIK',
            self::Sarpras => 'SPR',
        };
    }
}
