<?php

namespace App\Enums;

enum AssetStatus: string
{
    case InUse = 'Digunakan';
    case NotInUse = 'Tidak Digunakan';
    case Damaged = 'Rusak';
    case Borrowed = 'Dipinjam';
}
