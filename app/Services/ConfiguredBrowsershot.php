<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

class ConfiguredBrowsershot extends Browsershot
{
    private string $temporaryDirectory;

    public function __construct(string $url = '', bool $deviceEmulate = false)
    {
        parent::__construct($url, $deviceEmulate);

        $this->temporaryDirectory = storage_path('app/browsershot-temp');
        File::ensureDirectoryExists($this->temporaryDirectory, 0700, true);

        if (! is_writable($this->temporaryDirectory)) {
            throw new RuntimeException('Browsershot temporary directory is not writable.');
        }

        $this->setCustomTempPath($this->temporaryDirectory);
        $this->setNodeEnv(['PUPPETEER_TMP_DIR' => $this->temporaryDirectory]);
    }

    /** @return array<string, string|false> */
    protected function getWindowsEnv(): array
    {
        // Browsershot 5.4 omits setNodeEnv() from its Windows command.
        return ['PUPPETEER_TMP_DIR' => $this->temporaryDirectory] + parent::getWindowsEnv();
    }
}
