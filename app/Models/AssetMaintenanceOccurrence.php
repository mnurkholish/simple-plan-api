<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssetMaintenanceOccurrence extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = MaintenanceStatus::Terjadwal->value;

    public const STATUS_COMPLETED = MaintenanceStatus::Selesai->value;

    public const STATUS_OVERDUE = MaintenanceStatus::Terlambat->value;

    protected $table = 'asset_maintenance_occurrences';

    protected $fillable = [
        'asset_maintenance_id',
        'asset_code_id',
        'asset_maintenance_log_id',
        'period',
        'scheduled_date',
        'status',
        'realized_at',
        'is_late',
    ];

    protected $attributes = [
        'status' => self::STATUS_SCHEDULED,
        'is_late' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $occurrence): void {
            $rawScheduledDate = $occurrence->attributes['scheduled_date'] ?? $occurrence->scheduled_date;

            if ($rawScheduledDate instanceof \DateTimeInterface) {
                $occurrence->attributes['scheduled_date'] = $rawScheduledDate->format('Y-m-d');
            } elseif (is_string($rawScheduledDate) && $rawScheduledDate !== '') {
                $occurrence->attributes['scheduled_date'] = Carbon::parse($rawScheduledDate)->toDateString();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => MaintenancePeriod::class,
            'scheduled_date' => 'date:Y-m-d',
            'status' => MaintenanceStatus::class,
            'realized_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenance::class, 'asset_maintenance_id');
    }

    public function assetCode(): BelongsTo
    {
        return $this->belongsTo(AssetCode::class, 'asset_code_id');
    }

    public function asset(): HasOne
    {
        return $this->hasOne(Asset::class, 'asset_code_id', 'asset_code_id');
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenanceLog::class, 'asset_maintenance_log_id');
    }

    public function isRealized(): bool
    {
        return $this->asset_maintenance_log_id !== null
            || $this->realized_at !== null
            || $this->status === MaintenanceStatus::Selesai;
    }

    public function wasRealizedLate(): bool
    {
        if (! $this->isRealized()) {
            return false;
        }

        if ($this->realized_at !== null && $this->scheduled_date !== null) {
            return $this->realized_at->copy()->startOfDay()->gt($this->scheduled_date->copy()->startOfDay());
        }

        return (bool) $this->is_late;
    }

    public function isOverdue(CarbonInterface|string|null $asOf = null): bool
    {
        if ($this->isRealized()) {
            return false;
        }

        if ($this->scheduled_date === null) {
            return $this->status === MaintenanceStatus::Terlambat;
        }

        $referenceDate = $this->resolveReferenceDate($asOf);

        return $this->scheduled_date->copy()->startOfDay()->lt($referenceDate);
    }

    public function resolveEffectiveStatus(CarbonInterface|string|null $asOf = null): MaintenanceStatus
    {
        if ($this->isRealized()) {
            return MaintenanceStatus::Selesai;
        }

        if ($this->isOverdue($asOf)) {
            return MaintenanceStatus::Terlambat;
        }

        return MaintenanceStatus::Terjadwal;
    }

    public function overdueDays(CarbonInterface|string|null $asOf = null): int
    {
        if ($this->scheduled_date === null) {
            return 0;
        }

        $scheduledDate = $this->scheduled_date->copy()->startOfDay();

        if ($this->realized_at !== null) {
            $comparisonDate = $this->realized_at->copy()->startOfDay();
        } else {
            $comparisonDate = $this->resolveReferenceDate($asOf);
        }

        return max(0, (int) $scheduledDate->diffInDays($comparisonDate, false));
    }

    public function scopeScheduledBetween(
        Builder $query,
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null
    ): Builder {
        if ($from !== null && $from !== '') {
            $fromDate = $from instanceof CarbonInterface ? $from->toDateString() : Carbon::parse($from)->toDateString();
            $query->whereDate('asset_maintenance_occurrences.scheduled_date', '>=', $fromDate);
        }

        if ($to !== null && $to !== '') {
            $toDate = $to instanceof CarbonInterface ? $to->toDateString() : Carbon::parse($to)->toDateString();
            $query->whereDate('asset_maintenance_occurrences.scheduled_date', '<=', $toDate);
        }

        return $query;
    }

    public function scopeRealizedBetween(
        Builder $query,
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null
    ): Builder {
        if ($from !== null && $from !== '') {
            $fromDate = $from instanceof CarbonInterface ? $from->toDateString() : Carbon::parse($from)->toDateString();
            $query->whereDate('asset_maintenance_occurrences.realized_at', '>=', $fromDate);
        }

        if ($to !== null && $to !== '') {
            $toDate = $to instanceof CarbonInterface ? $to->toDateString() : Carbon::parse($to)->toDateString();
            $query->whereDate('asset_maintenance_occurrences.realized_at', '<=', $toDate);
        }

        return $query;
    }

    public function scopeWithStatus(Builder $query, MaintenanceStatus|string $status): Builder
    {
        $statusValue = $status instanceof MaintenanceStatus ? $status->value : $status;

        return $query->where('asset_maintenance_occurrences.status', $statusValue);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('asset_maintenance_occurrences.status', self::STATUS_SCHEDULED);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('asset_maintenance_occurrences.status', self::STATUS_COMPLETED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('asset_maintenance_occurrences.asset_maintenance_log_id')
            ->whereNull('asset_maintenance_occurrences.realized_at')
            ->where('asset_maintenance_occurrences.status', '!=', self::STATUS_COMPLETED);
    }

    public function scopeOverdue(Builder $query, CarbonInterface|string|null $asOf = null): Builder
    {
        $referenceDate = $this->resolveReferenceDate($asOf)->toDateString();

        return $query
            ->pending()
            ->where(function (Builder $builder) use ($asOf, $referenceDate): void {
                $builder->whereDate('asset_maintenance_occurrences.scheduled_date', '<', $referenceDate);

                if ($asOf === null) {
                    $builder->orWhere('asset_maintenance_occurrences.status', self::STATUS_OVERDUE);
                }
            });
    }

    public function scopeRealizedLate(Builder $query): Builder
    {
        return $query
            ->completed()
            ->where('asset_maintenance_occurrences.is_late', true);
    }

    public function scopeForMaintenance(Builder $query, AssetMaintenance|int $maintenance): Builder
    {
        $maintenanceId = $maintenance instanceof AssetMaintenance ? $maintenance->id : $maintenance;

        return $query->where('asset_maintenance_occurrences.asset_maintenance_id', $maintenanceId);
    }

    public function scopeForAssetCode(Builder $query, AssetCode|int $assetCode): Builder
    {
        $assetCodeId = $assetCode instanceof AssetCode ? $assetCode->id : $assetCode;

        return $query->where('asset_maintenance_occurrences.asset_code_id', $assetCodeId);
    }

    public function scopeForAsset(Builder $query, Asset|int $asset): Builder
    {
        if ($asset instanceof Asset) {
            return $query->where('asset_maintenance_occurrences.asset_code_id', $asset->asset_code_id);
        }

        return $query->whereHas('asset', fn (Builder $assetQuery): Builder => $assetQuery->where('assets.id', $asset));
    }

    public function scopeForClassification(Builder $query, AssetClassification|int $classification): Builder
    {
        $classificationId = $classification instanceof AssetClassification ? $classification->id : $classification;

        return $query->whereHas(
            'assetCode',
            fn (Builder $codeQuery): Builder => $codeQuery->where('asset_classification_id', $classificationId)
        );
    }

    public function scopeForLocation(Builder $query, AssetLocation|int $location): Builder
    {
        $locationId = $location instanceof AssetLocation ? $location->id : $location;

        return $query->whereHas(
            'assetCode',
            fn (Builder $codeQuery): Builder => $codeQuery->where('asset_location_id', $locationId)
        );
    }

    public function scopeForOfficer(Builder $query, User|int $officer): Builder
    {
        $officerId = $officer instanceof User ? $officer->id : $officer;

        return $query->whereHas(
            'maintenance',
            fn (Builder $maintenanceQuery): Builder => $maintenanceQuery->where('assigned_officer_id', $officerId)
        );
    }

    public function scopeVisibleTo(Builder $query, ?User $actor = null): Builder
    {
        if (! $actor) {
            return $query;
        }

        if ($actor->hasAnyRole(['super-admin', 'super_admin'])) {
            return $query->whereHas('assetCode.classification', fn (Builder $q): Builder => $q->where('name', 'Alat IT'));
        }

        if ($actor->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])) {
            return $query->whereHas('assetCode.classification', fn (Builder $q): Builder => $q->where('name', '!=', 'Alat IT'));
        }

        if ($actor->hasAnyRole(['petugas-tik', 'petugas_tik', 'petugas-sarpras', 'petugas_sarpras'])) {
            return $query->whereHas('maintenance', fn (Builder $q): Builder => $q->where('assigned_officer_id', $actor->id));
        }

        return $query;
    }

    protected function resolveReferenceDate(CarbonInterface|string|null $asOf = null): Carbon
    {
        if ($asOf instanceof CarbonInterface) {
            return Carbon::instance($asOf)->startOfDay();
        }

        if (is_string($asOf) && $asOf !== '') {
            return Carbon::parse($asOf)->startOfDay();
        }

        return today()->startOfDay();
    }
}
