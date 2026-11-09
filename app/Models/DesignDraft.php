<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignDraft extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'design_request_id',
        'file_object_key',
        'file_original_name',
        'file_mime_type',
        'file_size',
        'notes',
        'uploaded_by_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function designRequest(): BelongsTo
    {
        return $this->belongsTo(DesignRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DesignRevision::class);
    }
}
