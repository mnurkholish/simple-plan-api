<?php

namespace App\Enums;

enum MaintenancePeriod: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannually = 'semiannually';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
            self::Quarterly => '3 Bulan (Triwulan)',
            self::Semiannually => '6 Bulan (Semester)',
            self::Yearly => '1 Tahun (Tahunan)',
        };
    }
}
