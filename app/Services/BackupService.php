<?php

namespace App\Services;

use App\Jobs\RunBackup;
use App\Models\BackupHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class BackupService
{
    /**
     * @return array{backups: array<int, array<string, mixed>>, schedule_config: array{frequency: string, time: string}}
     */
    public function index(): array
    {
        return [
            'backups' => $this->backups(),
            'schedule_config' => $this->scheduleConfig(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function backups(): array
    {
        $disk = $this->disk();
        $backupName = $this->backupName();

        return collect($disk->files($backupName))
            ->filter(fn (string $file): bool => pathinfo($file, PATHINFO_EXTENSION) === 'zip')
            ->map(fn (string $file): array => [
                'name' => basename($file),
                'path' => $file,
                'size' => $this->formatSize($disk->size($file)),
                'size_bytes' => $disk->size($file),
                'date' => Carbon::createFromTimestamp($disk->lastModified($file))->format('Y-m-d H:i:s'),
                'created_at' => Carbon::createFromTimestamp($disk->lastModified($file))->toISOString(),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function create(int $performedById): ?BackupHistory
    {
        try {
            return Cache::lock('backup:enqueue', 120)->block(5, function () use ($performedById): ?BackupHistory {
                if (BackupHistory::whereIn('status', ['pending', 'processing'])->exists()) {
                    return null;
                }

                $runningLock = Cache::lock('backup:run', 10);
                if (! $runningLock->get()) {
                    return null;
                }
                $runningLock->release();

                $history = BackupHistory::create([
                    'type' => 'manual',
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

    /**
     * @param  array{frequency: string, time: string}  $data
     * @return array{frequency: string, time: string}
     */
    public function saveSchedule(array $data): array
    {
        $config = [
            'frequency' => $data['frequency'],
            'time' => $data['time'],
        ];

        $scheduleFile = $this->scheduleFile();
        $directory = dirname($scheduleFile);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($scheduleFile, json_encode($config, JSON_PRETTY_PRINT));

        return $config;
    }

    /**
     * @return array{frequency: string, time: string}
     */
    public function scheduleConfig(): array
    {
        $default = ['frequency' => 'none', 'time' => '00:00'];
        $scheduleFile = $this->scheduleFile();

        if (! file_exists($scheduleFile)) {
            return $default;
        }

        $config = json_decode((string) file_get_contents($scheduleFile), true);

        return is_array($config)
            ? array_merge($default, array_intersect_key($config, $default))
            : $default;
    }

    public function downloadPath(string $path): string
    {
        $path = $this->validateBackupPath($path);

        if (! $this->disk()->exists($path)) {
            throw ValidationException::withMessages([
                'path' => ['File backup tidak ditemukan.'],
            ]);
        }

        return $path;
    }

    public function delete(string $path): void
    {
        $path = $this->downloadPath($path);

        $this->disk()->delete($path);
    }

    public function diskName(): string
    {
        return config('backup.backup.destination.disks.0', 'local');
    }

    private function backupName(): string
    {
        return trim((string) config('backup.backup.name', 'laravel-backup'), '/');
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function scheduleFile(): string
    {
        return config('backup.schedule_file', storage_path('app/backup_schedule.json'));
    }

    private function validateBackupPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $backupName = $this->backupName();

        if (
            str_contains($path, '..')
            || ! str_starts_with($path, $backupName.'/')
            || pathinfo($path, PATHINFO_EXTENSION) !== 'zip'
        ) {
            throw ValidationException::withMessages([
                'path' => ['Path backup tidak valid.'],
            ]);
        }

        return $path;
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2).' '.$units[$pow];
    }
}
