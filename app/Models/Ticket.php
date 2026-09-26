<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'asset_id',
        'description',
        'status',
        'priority',
        'classified_by_id',
        'classified_at',
        'assigned_officer_id',
        'assigned_at',
        'sla_started_at',
        'sla_deadline',
        'completed_at',
        'closed_at',
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
            'priority' => TicketPriority::class,
            'initial_evidence_size' => 'integer',
            'classified_at' => 'datetime',
            'assigned_at' => 'datetime',
            'sla_started_at' => 'datetime',
            'sla_deadline' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasAnyRole(['super-admin', 'management'])) {
            return $query;
        }

        if ($actor->hasRole('koordinator-sarpras')) {
            return $query->where('service', TicketService::Sarpras->value);
        }

        if ($actor->hasRole('petugas-tik')) {
            return $query->where(function (Builder $query) use ($actor): void {
                $query
                    ->where('reporter_id', $actor->getKey())
                    ->orWhere(function (Builder $query) use ($actor): void {
                        $query
                            ->where('service', TicketService::Tik->value)
                            ->where('assigned_officer_id', $actor->getKey());
                    });
            });
        }

        if ($actor->hasRole('petugas-sarpras')) {
            return $query->where(function (Builder $query) use ($actor): void {
                $query
                    ->where('reporter_id', $actor->getKey())
                    ->orWhere(function (Builder $query) use ($actor): void {
                        $query
                            ->where('service', TicketService::Sarpras->value)
                            ->where('assigned_officer_id', $actor->getKey());
                    });
            });
        }

        return $query->where('reporter_id', $actor->getKey());
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function classifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by_id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function tikDetail(): HasOne
    {
        return $this->hasOne(TicketTikDetail::class);
    }

    public function sarprasDetail(): HasOne
    {
        return $this->hasOne(TicketSarprasDetail::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function handlings(): HasMany
    {
        return $this->hasMany(TicketHandling::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class)
            ->orderByDesc('escalated_at')
            ->orderByDesc('id');
    }
}
