<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

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

    /**
     * @return array{message: string, output: string}
     */
    public function create(): array
    {
        if (config('database.default') === 'sqlite') {
            return $this->createSqliteBackup();
        }

        $exitCode = Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Backup command gagal dijalankan.');
        }

        return [
            'message' => 'Backup database berhasil dibuat.',
            'output' => trim(Artisan::output()),
        ];
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

    /**
     * @return array{message: string, output: string}
     */
    private function createSqliteBackup(): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extension PHP zip belum aktif.');
        }

        $databasePath = (string) config('database.connections.sqlite.database');

        if ($databasePath === ':memory:' || ! is_file($databasePath)) {
            throw new RuntimeException('File database SQLite tidak ditemukan.');
        }

        $temporaryDirectory = storage_path('app/backup-temp');

        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0755, true);
        }

        $filename = config('backup.backup.destination.filename_prefix', '')
            .now()->format('Y-m-d-H-i-s')
            .'.zip';
        $temporaryPath = $temporaryDirectory.DIRECTORY_SEPARATOR.$filename;
        $zip = new ZipArchive;

        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File zip backup gagal dibuat.');
        }

        $zip->addFile($databasePath, basename($databasePath));
        $zip->close();

        $backupPath = $this->backupName().'/'.$filename;
        $this->disk()->put($backupPath, (string) file_get_contents($temporaryPath));
        unlink($temporaryPath);

        return [
            'message' => 'Backup database berhasil dibuat.',
            'output' => 'SQLite database file archived to '.$backupPath.'.',
        ];
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
