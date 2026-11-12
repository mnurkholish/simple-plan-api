<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssetCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_classification_id',
        'asset_location_id',
        'asset_item_id',
        'sequence_number',
        'full_code',
    ];

    public function classification(): BelongsTo
    {
        return $this->belongsTo(AssetClassification::class, 'asset_classification_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class, 'asset_item_id');
    }

    public function assetItem(): BelongsTo
    {
        return $this->item();
    }

    public function asset(): HasOne
    {
        return $this->hasOne(Asset::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $actor = null): Builder
    {
        if (! $actor) {
            return $query;
        }

        if ($actor->hasAnyRole(['super-admin', 'super_admin'])) {
            return $query->whereDoesntHave('classification', function (Builder $q): void {
                $q->where(function (Builder $sub): void {
                    $sub->whereIn('name', ['Alat Sarpras', 'Sarpras'])
                        ->orWhere('name', 'like', '%sarpras%')
                        ->orWhere('name', 'like', '%Alat Sarpras%');
                });
            });
        }

        if ($actor->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])) {
            return $query->whereHas('classification', fn (Builder $q): Builder => $q->where('name', '!=', 'Alat IT')->where('name', 'not like', '%IT%')->where('name', 'not like', '%TIK%'));
        }

        if ($actor->hasAnyRole(['manajemen', 'management'])) {
            return $query->whereDoesntHave('classification', function (Builder $q): void {
                $q->where(function (Builder $sub): void {
                    $sub->whereIn('name', ['Alat IT', 'TIK', 'IT', 'Alat Sarpras', 'Sarpras'])
                        ->orWhere('name', 'like', '%TIK%')
                        ->orWhere('name', 'like', '%Sarpras%')
                        ->orWhere('name', 'like', '%Alat IT%')
                        ->orWhere('name', 'like', '%Alat Sarpras%');
                });
            });
        }

        return $query;
    }
}
