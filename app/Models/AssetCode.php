<?php

namespace App\Models;

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
}
