<?php

namespace App\Models;

use App\Enums\AssetCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_maintenance_id',
        'scheduled_date',
        'work_description',
        'maintenance_result',
        'post_maintenance_condition',
        'started_at',
        'completed_at',
        'proof_photo_object_key',
        'proof_photo_original_name',
        'proof_photo_mime_type',
        'proof_photo_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'post_maintenance_condition' => AssetCondition::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenance::class, 'asset_maintenance_id');
    }
}
