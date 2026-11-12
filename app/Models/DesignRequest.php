<?php

namespace App\Models;

use App\Enums\DesignRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'request_number',
        'title',
        'design_type',
        'description',
        'needed_at',
        'requester_id',
        'unit_id',
        'status',
        'priority',
        'assigned_officer_id',
        'assigned_at',
        'rejection_note',
        'completed_at',
        'reference_file_object_key',
        'reference_file_original_name',
        'reference_file_mime_type',
        'reference_file_size',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DesignRequestStatus::class,
            'needed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
            'reference_file_size' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(DesignDraft::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DesignRevision::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin')) {
            return $query;
        }

        if ($user->hasRole('petugas-tik')) {
            return $query->where('assigned_officer_id', $user->id);
        }

        return $query->where('requester_id', $user->id);
    }
}
