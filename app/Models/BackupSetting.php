<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $fillable = [
        'is_active',
        'frequency',
        'backup_time',
        'day_of_week',
        'day_of_month',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
        ];
    }
}
