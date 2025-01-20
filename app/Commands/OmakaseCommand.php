<?php

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;

class OmakaseCommand extends Command
{
    protected $signature = 'laravel:omakase';

    protected $description = 'Run the omakase command';

    public function handle(): int
    {
        //
        // Install Composer packages
        // -------------------------------------------------------------------------------

        if (! file_exists(base_path('composer.json'))) {
            $this->error('composer.json not found in project root.');

            return self::FAILURE;
        }

        $this->info('Installing Composer packages...');

        $composerPackages = [
            'livewire/livewire',
            'laravel/horizon',
        ];

        if (! $this->installComposerPackages($composerPackages)) {
            return self::FAILURE;
        }

        $composerDevPackages = [
            'laravel/pint',
            'larastan/larastan',
            'barryvdh/laravel-ide-helper',
            'pestphp/pest',
        ];

        if (! $this->installComposerDevPackages($composerDevPackages)) {
            return self::FAILURE;
        }

        $this->info('Packages installed successfully');

        //
        // Install NPM packages
        // -------------------------------------------------------------------------------

        $this->info('Installing NPM packages...');

        $npmPackages = [
        ];

        if (! $this->installNpmPackages($npmPackages, false)) {
            return self::FAILURE;
        }

        $npmDevPackages = [
            'prettier',
            'prettier-plugin-blade',
            'prettier-plugin-tailwindcss',
        ];

        if (! $this->installNpmPackages($npmDevPackages, true)) {
            return self::FAILURE;
        }

        $this->info('NPM packages installed successfully');

        //
        // Create configuration files
        // -------------------------------------------------------------------------------

        $this->info('Creating configuration files...');

        $configFiles = [
            '.prettierrc',
            'phpstan.neon',
            'pint.json',
        ];

        foreach ($configFiles as $file) {
            $this->copyFile($file);
        }

        //
        // Create Github workflows
        // -------------------------------------------------------------------------------

        $this->info('Creating Github workflows...');

        $githubWorkflows = [
            '.github/workflows/dependabot-automerge.yml',
            '.github/workflows/phpstan.yml',
            '.github/workflows/pint.yml',
            '.github/workflows/release.yml',
            '.github/dependabot.yml',
        ];

        foreach ($githubWorkflows as $workflow) {
            $this->copyFile($workflow);
        }

        return self::SUCCESS;
    }

    /** @param array<string> $packages */
    protected function installComposerDevPackages(array $packages): bool
    {
        return $this->installComposerPackages($packages, true);
    }

    /** @param array<string> $packages */
    protected function installComposerPackages(array $packages, bool $dev = false): bool
    {
        $command = ['composer', 'require'];
        if ($dev) {
            $command[] = '--dev';
        }

        $process = new \Symfony\Component\Process\Process(array_merge(
            $command,
            $packages
        ));

        if (PHP_OS_FAMILY !== 'Windows') {
            $process->setTty(true);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to install packages');

            return false;
        }

        return true;
    }

    /** @param array<string> $packages */
    protected function installNpmDevPackages(array $packages): bool
    {
        return $this->installNpmPackages($packages, true);
    }

    /** @param array<string> $packages */
    protected function installNpmPackages(array $packages, bool $dev = false): bool
    {
        $command = ['npm', 'install'];
        if ($dev) {
            $command[] = '--save-dev';
        }

        $process = new \Symfony\Component\Process\Process(array_merge(
            $command,
            $packages
        ), base_path());

        if (PHP_OS_FAMILY !== 'Windows') {
            $process->setTty(true);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to install NPM packages');
            $this->error($process->getErrorOutput());

            return false;
        }

        return true;
    }

    protected function copyFile(string $filename): void
    {
        $destinationPath = base_path($filename);

        // Ensure target directory exists
        $directory = dirname($destinationPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Copy the file
        $sourceFile = __DIR__.'/../../'.$filename;
        $contents = file_get_contents($sourceFile);
        file_put_contents($destinationPath, $contents);

        $this->info("Created '{$filename}' at: {$destinationPath}");
    }
}
