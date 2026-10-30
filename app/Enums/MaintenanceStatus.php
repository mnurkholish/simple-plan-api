<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Terjadwal = 'terjadwal';
    case Selesai = 'selesai';
    case Terlambat = 'terlambat';

    public function label(): string
    {
        return match ($this) {
            self::Terjadwal => 'Terjadwal',
            self::Selesai => 'Selesai',
            self::Terlambat => 'Terlambat',
        };
    }
}
