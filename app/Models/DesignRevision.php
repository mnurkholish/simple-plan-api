<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignRevision extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'design_request_id',
        'design_draft_id',
        'revision_note',
        'requested_by_id',
        'note',
    ];

    public function getNoteAttribute(): ?string
    {
        return $this->attributes['revision_note'] ?? null;
    }

    public function setNoteAttribute(?string $value): void
    {
        $this->attributes['revision_note'] = $value;
    }

    public function designRequest(): BelongsTo
    {
        return $this->belongsTo(DesignRequest::class);
    }

    public function designDraft(): BelongsTo
    {
        return $this->belongsTo(DesignDraft::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}
