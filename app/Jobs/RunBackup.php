<?php

namespace App\Jobs;

use App\Models\BackupHistory;
use App\Services\BackupArchiveStagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RunBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly int $historyId)
    {
        $this->timeout = (int) config('backup.job_timeout', 3600);
    }

    public function handle(BackupArchiveStagingService $staging): void
    {
        $history = BackupHistory::findOrFail($this->historyId);
        $lock = null;
        $locked = false;

        try {
            $this->assertConfiguration();

            $lock = Cache::lock('backup:run', $this->timeout + 120);
            $locked = $lock->get();
            if (! $locked) {
                throw new RuntimeException('Proses backup lain sedang berjalan.');
            }

            $history->update(['status' => 'processing', 'started_at' => now()]);
            $staging->setHistoryId($history->id);

            $filename = 'backup-'.$history->id.'-'.now()->format('Y-m-d-H-i-s').'.zip';
            $exitCode = Artisan::call('backup:run', [
                '--filename' => $filename,
                '--only-to-disk' => 'backup',
                '--disable-notifications' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException($staging->failureMessage() ?? 'Pembuatan arsip backup gagal. Periksa log server.');
            }

            $disk = Storage::disk('backup');
            $objectKey = trim((string) config('backup.backup.name'), '/').'/'
                .config('backup.backup.destination.filename_prefix', '').$filename;

            if (! $disk->exists($objectKey)) {
                throw new RuntimeException('Arsip backup tidak ditemukan pada bucket backup.');
            }

            $fileSize = $disk->size($objectKey);
            if ($fileSize < 1) {
                throw new RuntimeException('Arsip backup kosong pada bucket backup.');
            }

            $history->update([
                'status' => 'success',
                'file_name' => basename($objectKey),
                'disk' => 'backup',
                'object_key' => $objectKey,
                'file_size' => $fileSize,
                'finished_at' => now(),
                'failure_message' => null,
            ]);
        } catch (Throwable $exception) {
            $history->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failure_message' => in_array($exception->getMessage(), [
                    'Proses backup lain sedang berjalan.',
                    'Gagal mengambil file aplikasi dari MinIO.',
                    'Gagal menulis metadata backup.',
                ], true) ? $exception->getMessage() : 'Proses backup gagal. Periksa log server.',
            ]);

            throw $exception;
        } finally {
            $staging->setHistoryId(null);

            if ($locked) {
                try {
                    Storage::disk('backup_temp')->deleteDirectory('temp');
                } catch (Throwable $exception) {
                    report($exception);
                }

                $lock->release();
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        BackupHistory::whereKey($this->historyId)
            ->whereNotIn('status', ['success', 'failed'])
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failure_message' => 'Proses backup terhenti. Periksa log server.',
            ]);
    }

    private function assertConfiguration(): void
    {
        $sourceBucket = config('filesystems.disks.s3.bucket');
        $backupBucket = config('filesystems.disks.backup.bucket');

        if (! $sourceBucket || ! $backupBucket || $sourceBucket === $backupBucket) {
            throw new RuntimeException('Bucket file aplikasi dan bucket backup harus berbeda dan terkonfigurasi.');
        }

        if (config('database.default') !== 'mysql' || config('backup.backup.source.databases') !== ['mysql']) {
            throw new RuntimeException('Backup memerlukan koneksi database MySQL.');
        }

        if ($this->timeout < 1 || config('queue.connections.backup.retry_after') <= $this->timeout) {
            throw new RuntimeException('Timeout dan retry_after queue backup tidak valid.');
        }
    }
}
