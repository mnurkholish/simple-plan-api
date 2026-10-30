<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use RuntimeException;
use Spatie\Backup\Events\BackupManifestWasCreated;
use Throwable;

class BackupArchiveStagingService
{
    private ?int $historyId = null;

    private ?string $failureMessage = null;

    public function setHistoryId(?int $historyId): void
    {
        $this->historyId = $historyId;
        $this->failureMessage = null;
    }

    public function failureMessage(): ?string
    {
        return $this->failureMessage;
    }

    public function stage(BackupManifestWasCreated $event): void
    {
        if ($this->historyId === null) {
            return;
        }

        if ($event->manifest->count() === 0) {
            throw new RuntimeException('Dump database tidak ditemukan dalam manifest backup.');
        }

        $source = Storage::disk('s3');
        $temporary = Storage::disk('backup_temp');
        $manifestDirectory = dirname($event->manifest->path());
        $dumpDirectory = $manifestDirectory.DIRECTORY_SEPARATOR.'db-dumps'.DIRECTORY_SEPARATOR;
        $localFileCount = 0;

        foreach ($event->manifest->files() as $file) {
            if (is_file($file) && ! str_starts_with($file, $dumpDirectory)) {
                $localFileCount++;
            }
        }

        $fileCount = 0;

        try {
            foreach ($source->getDriver()->listContents('', true) as $entry) {
                if (! $entry instanceof FileAttributes) {
                    continue;
                }

                $key = $entry->path();
                if ($key === '' || $key !== rtrim($key) || strpbrk($key, "\\\r\n") !== false || preg_match('~(^|/)\.{1,2}(/|$)~', $key)) {
                    throw new RuntimeException('Path file MinIO tidak valid untuk backup.');
                }

                $path = 'temp/minio/'.$key;
                $stream = null;

                try {
                    $stream = $source->readStream($key);
                    if (! is_resource($stream) || ! $temporary->put($path, $stream)) {
                        throw new RuntimeException('Transfer file MinIO gagal.');
                    }

                    $expectedSize = $entry->fileSize() ?? $source->size($key);
                    if ($temporary->size($path) !== $expectedSize) {
                        throw new RuntimeException('Ukuran file MinIO tidak sesuai setelah transfer.');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $event->manifest->addFiles($manifestDirectory.DIRECTORY_SEPARATOR.'minio/'.$key);
                $fileCount++;
            }
        } catch (Throwable $exception) {
            $this->failureMessage = 'Gagal mengambil file aplikasi dari MinIO.';
            throw new RuntimeException($this->failureMessage, previous: $exception);
        }

        $metadataPath = 'temp/backup-metadata.json';
        $metadata = json_encode([
            'backup_history_id' => $this->historyId,
            'created_at' => now()->toIso8601String(),
            'database_connections' => config('backup.backup.source.databases'),
            'source_disk' => 's3',
            'file_count' => $localFileCount + $fileCount,
            'minio_file_count' => $fileCount,
            'local_file_count' => $localFileCount,
        ], JSON_THROW_ON_ERROR);

        try {
            if (! $temporary->put($metadataPath, $metadata)) {
                throw new RuntimeException('Gagal menulis metadata backup.');
            }
        } catch (Throwable $exception) {
            $this->failureMessage = 'Gagal menulis metadata backup.';
            throw new RuntimeException($this->failureMessage, previous: $exception);
        }

        $event->manifest->addFiles($manifestDirectory.DIRECTORY_SEPARATOR.'backup-metadata.json');
    }
}
