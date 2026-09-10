<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Baru = 'baru';
    case Terverifikasi = 'terverifikasi';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
}
