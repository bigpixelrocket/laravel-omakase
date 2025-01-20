<?php

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;

class LaravelOmakaseCommand extends Command
{
    protected $signature = 'laravelomakase';

    protected $description = 'Run the omakase command';

    public function handle(): int
    {
        $configFiles = [
            'phpstan.neon',
            'pint.json',
        ];

        foreach ($configFiles as $file) {
            $this->ensureConfigFile($file);
        }

        return self::SUCCESS;
    }

    protected function ensureConfigFile(string $filename): void
    {
        /** @var string */
        $destPath = base_path($filename);

        if (! file_exists($destPath)) {
            /** @var string */
            $defaultConfig = file_get_contents(__DIR__.'/../../'.$filename);
            file_put_contents($destPath, $defaultConfig);
            $this->info(sprintf('Created %s file at: %s', $filename, $destPath));
        } else {
            $this->warn(sprintf('%s file already exists', $filename));
        }
    }
}
