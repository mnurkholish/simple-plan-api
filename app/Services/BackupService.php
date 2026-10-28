<?php

namespace App\Services;

use App\Jobs\RunBackup;
use App\Models\BackupHistory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupService
{
    /** @param array<string, mixed> $filters */
    public function histories(array $filters, int $perPage): LengthAwarePaginator
    {
        $from = isset($filters['date_from'])
            ? CarbonImmutable::parse($filters['date_from'], 'Asia/Jakarta')->startOfDay()->utc()
            : null;
        $to = isset($filters['date_to'])
            ? CarbonImmutable::parse($filters['date_to'], 'Asia/Jakarta')->addDay()->startOfDay()->utc()
            : null;

        return BackupHistory::query()
            ->with('performedBy:id,name')
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function downloadableHistory(int $id): BackupHistory
    {
        $history = BackupHistory::findOrFail($id);

        abort_unless($history->status === 'success', 409, 'Backup belum berhasil.');
        abort_unless($history->disk === 'backup' && filled($history->object_key), 404, 'File backup tidak ditemukan.');
        abort_unless(Storage::disk('backup')->exists($history->object_key), 404, 'File backup tidak ditemukan.');

        return $history;
    }

    public function deleteArchive(int $id): void
    {
        $history = BackupHistory::findOrFail($id);

        abort_unless($history->status === 'success', 409, 'Backup belum berhasil.');

        if (! filled($history->object_key)) {
            return;
        }

        abort_unless($history->disk === 'backup', 404, 'File backup tidak ditemukan.');

        if (! Storage::disk('backup')->delete($history->object_key)) {
            throw new RuntimeException('Gagal menghapus arsip backup dari storage.');
        }

        $history->update(['disk' => null, 'object_key' => null]);
    }

    public function create(int $performedById): ?BackupHistory
    {
        return $this->enqueue('manual', $performedById);
    }

    public function createAutomatic(): ?BackupHistory
    {
        return $this->enqueue('automatic', null);
    }

    private function enqueue(string $type, ?int $performedById): ?BackupHistory
    {
        try {
            return Cache::lock('backup:enqueue', 120)->block(5, function () use ($type, $performedById): ?BackupHistory {
                if (BackupHistory::whereIn('status', ['pending', 'processing'])->exists()) {
                    return null;
                }

                $runningLock = Cache::lock('backup:run', 10);
                if (! $runningLock->get()) {
                    return null;
                }
                $runningLock->release();

                $history = BackupHistory::create([
                    'type' => $type,
                    'performed_by_id' => $performedById,
                    'status' => 'pending',
                ]);

                try {
                    Bus::dispatch((new RunBackup($history->id))
                        ->onConnection('backup')
                        ->onQueue('backups'));
                } catch (Throwable $exception) {
                    $history->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'failure_message' => 'Gagal mengantrekan proses backup.',
                    ]);

                    throw $exception;
                }

                return $history;
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    public function history(int $id): BackupHistory
    {
        return BackupHistory::with('performedBy:id,name')->findOrFail($id);
    }
}
