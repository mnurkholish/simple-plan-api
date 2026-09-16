<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'rejection_reason',
        'priority',
        'assigned_officer_id',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => TicketService::class,
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'initial_evidence_size' => 'integer',
            'completed_at' => 'datetime',
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

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function handlings(): HasMany
    {
        return $this->hasMany(TicketHandling::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
