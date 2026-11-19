<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\DesignRequestStatus;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\AssetMaintenanceOccurrence;
use App\Models\BackupHistory;
use App\Models\DesignRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DashboardResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @return array<string, mixed> */
    public function getDashboardData(User $user): array
    {
        $variant = DashboardResolver::resolveFor($user);
        $today = CarbonImmutable::now(config('app.operational_timezone', 'Asia/Jakarta'));

        return match ($variant) {
            'super-admin', 'management' => [
                'variant' => $variant,
                'summary' => $this->systemSummary($today),
                'charts' => $this->charts(),
            ],
            'koordinator-sarpras' => [
                'variant' => $variant,
                'summary' => $this->coordinatorSummary($today),
                'charts' => $this->charts(true),
            ],
            'petugas-tik', 'petugas-sarpras' => [
                'variant' => $variant,
                'summary' => $this->officerSummary($user, $variant, $today),
            ],
            default => [
                'variant' => $variant,
                'summary' => $this->userSummary($user),
            ],
        };
    }

    /** @return array<string, int> */
    private function systemSummary(CarbonImmutable $today): array
    {
        return [
            'jumlah_tiket_baru' => Ticket::where('status', TicketStatus::Baru->value)->count(),
            'jumlah_tiket_diproses' => Ticket::where('status', TicketStatus::Diproses->value)->count(),
            'jumlah_request_desain_baru' => DesignRequest::where('status', DesignRequestStatus::Baru->value)->count(),
            'jumlah_aset_rusak' => Asset::where('status', AssetStatus::Damaged->value)->count(),
            'jumlah_jadwal_maintenance_minggu_ini' => $this->weeklyMaintenanceCount($today),
        ];
    }

    /** @return array<string, int|string|null> */
    private function coordinatorSummary(CarbonImmutable $today): array
    {
        $lastBackup = BackupHistory::query()->where('status', 'success')
            ->whereNotNull('finished_at')->orderByDesc('finished_at')->first(['finished_at']);

        return [
            'jumlah_tiket_sarpras_baru' => $this->tickets(TicketService::Sarpras)->where('status', TicketStatus::Baru->value)->count(),
            'jumlah_tiket_sarpras_diproses' => $this->tickets(TicketService::Sarpras)->where('status', TicketStatus::Diproses->value)->count(),
            'jumlah_aset_sarpras_rusak' => $this->assets(true)->where('status', AssetStatus::Damaged->value)->count(),
            'jumlah_jadwal_maintenance_minggu_ini' => $this->weeklyMaintenanceCount($today, true),
            'waktu_backup_terakhir' => $lastBackup?->finished_at?->timezone(config('app.operational_timezone', 'Asia/Jakarta'))->toIso8601String(),
        ];
    }

    /** @return array<string, int> */
    private function officerSummary(User $user, string $variant, CarbonImmutable $today): array
    {
        $service = $variant === 'petugas-tik' ? TicketService::Tik : TicketService::Sarpras;
        $assigned = fn (): Builder => $this->tickets($service)->where('assigned_officer_id', $user->getKey());

        $summary = [
            'jumlah_tiket_ditugaskan' => $assigned()->whereIn('status', [
                TicketStatus::Baru->value,
                TicketStatus::Diklasifikasi->value,
                TicketStatus::Diproses->value,
                TicketStatus::Eskalasi->value,
            ])->count(),
            'jumlah_tiket_diproses' => $assigned()->where('status', TicketStatus::Diproses->value)->count(),
        ];

        if ($service === TicketService::Tik) {
            $summary['jumlah_tugas_desain_aktif'] = DesignRequest::query()
                ->where('assigned_officer_id', $user->getKey())
                ->whereNotIn('status', [DesignRequestStatus::Selesai->value, DesignRequestStatus::Ditolak->value])
                ->count();
        }

        $summary['jumlah_jadwal_maintenance_mendatang'] = $this->maintenanceOccurrences($service === TicketService::Sarpras, $service === TicketService::Tik)
            ->pending()->whereDate('scheduled_date', '>=', $today->toDateString())
            ->whereHas('maintenance', fn (Builder $query): Builder => $query
                ->where('is_active', true)->where('assigned_officer_id', $user->getKey()))
            ->count();

        return $summary;
    }

    /** @return array<string, int> */
    private function userSummary(User $user): array
    {
        return [
            'jumlah_tiket_aktif' => Ticket::query()->where('reporter_id', $user->getKey())->whereIn('status', [
                TicketStatus::Baru->value,
                TicketStatus::Diklasifikasi->value,
                TicketStatus::Diproses->value,
                TicketStatus::Eskalasi->value,
            ])->count(),
            'jumlah_tiket_menunggu_verifikasi' => Ticket::query()->where('reporter_id', $user->getKey())
                ->where('status', TicketStatus::Terselesaikan->value)->count(),
            'jumlah_request_menunggu_persetujuan' => DesignRequest::query()->where('requester_id', $user->getKey())
                ->where('status', DesignRequestStatus::MenungguPersetujuan->value)->count(),
        ];
    }

    private function weeklyMaintenanceCount(CarbonImmutable $today, bool $sarprasOnly = false): int
    {
        return $this->maintenanceOccurrences($sarprasOnly)
            ->scheduledBetween($today->startOfWeek(), $today->endOfWeek())->count();
    }

    /** @return array<string, array<string, int>> */
    private function charts(bool $sarprasOnly = false): array
    {
        return [
            'tren_tiket_per_bulan' => $this->monthlyTicketTrend($sarprasOnly),
            'komposisi_status_aset' => $this->assetStatusComposition($sarprasOnly),
        ];
    }

    /** @return array<string, int> */
    private function monthlyTicketTrend(bool $sarprasOnly): array
    {
        $month = CarbonImmutable::now(config('app.operational_timezone', 'Asia/Jakarta'))->startOfMonth()->subMonths(5);
        $months = [];
        for ($index = 0; $index < 6; $index++) {
            $start = $month->addMonths($index);
            $months[$start->format('Y-m')] = $this->tickets($sarprasOnly ? TicketService::Sarpras : null)
                ->where('created_at', '>=', $start->setTimezone(config('app.timezone')))
                ->where('created_at', '<', $start->addMonth()->setTimezone(config('app.timezone')))
                ->count();
        }

        return $months;
    }

    /** @return array<string, int> */
    private function assetStatusComposition(bool $sarprasOnly): array
    {
        $counts = [];
        foreach (AssetStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($this->assets($sarprasOnly)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status') as $status => $count) {
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $count;
            }
        }

        return $counts;
    }

    private function tickets(?TicketService $service = null): Builder
    {
        return Ticket::query()->when($service, fn (Builder $query): Builder => $query->where('service', $service->value));
    }

    private function assets(bool $sarprasOnly = false): Builder
    {
        $query = Asset::query();
        if ($sarprasOnly) {
            $query->whereHas('assetCode.classification', fn (Builder $classification): Builder => $classification
                ->whereRaw('LOWER(TRIM(name)) != ?', ['alat it']));
        }

        return $query;
    }

    private function maintenanceOccurrences(bool $sarprasOnly = false, bool $tikOnly = false): Builder
    {
        $query = AssetMaintenanceOccurrence::query();
        if ($sarprasOnly) {
            $query->whereHas('assetCode.classification', fn (Builder $classification): Builder => $classification
                ->whereRaw('LOWER(TRIM(name)) != ?', ['alat it']));
        } elseif ($tikOnly) {
            $query->whereHas('assetCode.classification', fn (Builder $classification): Builder => $classification
                ->whereRaw('LOWER(TRIM(name)) = ?', ['alat it']));
        }

        return $query;
    }

    /** @return array<string, int> */
    public function getLiveStats(): array
    {
        $today = now()->format('Y-m-d');

        return [
            'online_users' => $this->onlineUsers(),
            'logins_today' => (int) Cache::get('login_today_'.$today, 0),
            'logouts_today' => (int) Cache::get('logout_today_'.$today, 0),
        ];
    }

    public function recordLogin(): void
    {
        $this->incrementDailyCounter('login_today_'.now()->format('Y-m-d'));
    }

    public function recordLogout(): void
    {
        $this->incrementDailyCounter('logout_today_'.now()->format('Y-m-d'));
    }

    private function onlineUsers(): int
    {
        return DB::table('personal_access_tokens')
            ->where(function ($query): void {
                $query->where('last_used_at', '>=', now()->subMinutes(5))
                    ->orWhere('created_at', '>=', now()->subMinutes(5));
            })->distinct('tokenable_id')->count('tokenable_id');
    }

    private function incrementDailyCounter(string $key): void
    {
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }
}
