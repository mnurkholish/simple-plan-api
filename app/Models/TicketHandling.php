<?php

namespace App\Models;

use Database\Factories\TicketHandlingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHandling extends Model
{
    /** @use HasFactory<TicketHandlingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'handled_by_id',
        'notes',
        'started_at',
        'completed_at',
        'result_photo_object_key',
        'result_photo_original_name',
        'result_photo_mime_type',
        'result_photo_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'result_photo_size' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }
}
