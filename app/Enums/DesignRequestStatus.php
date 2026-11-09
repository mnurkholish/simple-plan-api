<?php

namespace App\Enums;

enum DesignRequestStatus: string
{
    case Baru = 'baru';
    case Ditugaskan = 'ditugaskan';
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case PerluRevisi = 'perlu_revisi';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
}
