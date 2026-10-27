<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupHistory extends Model
{
    protected $fillable = [
        'type',
        'performed_by_id',
        'status',
        'file_name',
        'disk',
        'object_key',
        'file_size',
        'started_at',
        'finished_at',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }
}
