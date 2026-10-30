<?php

namespace App\Models;

use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetMaintenance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_code_id',
        'assigned_officer_id',
        'creator_id',
        'maintenance_type',
        'period',
        'next_scheduled_date',
        'status',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
        'status' => 'terjadwal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => MaintenancePeriod::class,
            'next_scheduled_date' => 'date:Y-m-d',
            'status' => MaintenanceStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function assetCode(): BelongsTo
    {
        return $this->belongsTo(AssetCode::class, 'asset_code_id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class, 'asset_maintenance_id');
    }
}
