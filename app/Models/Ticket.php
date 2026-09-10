<?php

namespace App\Models;

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_number',
        'service',
        'reporter_id',
        'unit_id',
        'category',
        'description',
        'status',
        'initial_evidence_object_key',
        'initial_evidence_original_name',
        'initial_evidence_mime_type',
        'initial_evidence_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => TicketService::class,
            'status' => TicketStatus::class,
            'initial_evidence_size' => 'integer',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
