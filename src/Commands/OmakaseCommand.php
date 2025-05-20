<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OmakaseCommand extends Command
{
    protected $signature = 'laravel:omakase
        {--composer : Install only composer packages}
        {--npm : Install only npm packages}
        {--files : Only copy files}
        {--force : Override existing files when copying}';

    protected $description = 'An opinionated menu for your next Laravel project';

    public function handle(): int
    {
        $runComposer = $this->option('composer');
        $runNpm = $this->option('npm');
        $runFiles = $this->option('files');

        // If no specific options are provided, run everything
        $runAll = ! $runComposer && ! $runNpm && ! $runFiles;

        if ($runAll || $runComposer) {
            $this->newLine();
            $this->line('╔═══════════════════════════════════════════╗');
            $this->line('║       Installing Composer Packages        ║');
            $this->line('╚═══════════════════════════════════════════╝');
            $this->newLine();

            $composerPackages = [
                'require' => [
                    'livewire/livewire' => [
                        ['php', 'artisan', 'livewire:publish', '--config'],
                    ],
                ],
                'require-dev' => [
                    'barryvdh/laravel-ide-helper',
                    'larastan/larastan',
                    'laravel/pint',
                    'pestphp/pest',
                    'soloterm/solo',
                ],
            ];

            if (! $this->installPackages($composerPackages, ['composer', 'require'], 'require-dev', '--dev')) {
                return self::FAILURE;
            }
        }

        if ($runAll || $runNpm) {
            $this->newLine();
            $this->line('╔═══════════════════════════════════════════╗');
            $this->line('║         Installing NPM Packages           ║');
            $this->line('╚═══════════════════════════════════════════╝');
            $this->newLine();

            $npmPackages = [
                'dependencies' => [
                ],
                'devDependencies' => [
                    'prettier',
                    'prettier-plugin-blade',
                    'prettier-plugin-tailwindcss',
                ],
            ];

            if (! $this->installPackages($npmPackages, ['npm', 'install'], 'devDependencies', '--save-dev')) {
                return self::FAILURE;
            }
        }

        if ($runAll || $runFiles) {
            $this->newLine();
            $this->line('╔═══════════════════════════════════════════╗');
            $this->line('║              Copying files                ║');
            $this->line('╚═══════════════════════════════════════════╝');
            $this->newLine();

            if (! $this->copyFiles()) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string|array<array<string>>>>  $packages
     * @param  array<string>  $command
     */
    protected function installPackages(array $packages, array $command, string $devFlag = '', string $devFlagValue = '--dev'): bool
    {
        foreach ($packages as $type => $typePackages) {
            $commands = [];
            $packageNames = [];

            /** @var string|array<array<string>> $v */
            foreach ($typePackages as $k => $v) {
                if (is_string($v)) {
                    $packageNames[] = $v;
                } else {
                    $packageNames[] = (string) $k;
                    $commands = [...$commands, ...$v];
                }
            }

            $baseCommand = $command;
            if ($type === $devFlag) {
                $baseCommand[] = $devFlagValue;
            }

            $commands = [[...$baseCommand, ...$packageNames], ...$commands];
            if (! $this->execCommands($commands)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array<string>>  $commands
     */
    protected function execCommands(array $commands): bool
    {
        foreach ($commands as $command) {
            $this->warn(implode(' ', $command));
            if (! $this->exec($command)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string>  $command
     */
    protected function exec(array $command): bool
    {
        $process = new \Symfony\Component\Process\Process($command);

        // Disable TTY interaction for Windows
        if (PHP_OS_FAMILY !== 'Windows') {
            $process->setTty(true);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
                $this->error('Failed to run command');
                $this->error($process->getErrorOutput());
            }

            return false;
        }

        return true;
    }

    protected function copyFiles(): bool
    {
        $basePath = __DIR__.'/../../dist/';

        /** @var array<int, string> $files */
        $files = $this->getDistFiles($basePath);

        try {
            foreach ($files as $filePathname) {
                $destPathname = base_path(explode($basePath, (string) $filePathname)[1]);
                $destDirname = dirname($destPathname);
                $relativeDest = str_replace(base_path().'/', '', $destPathname);

                $fileExists = File::exists($destPathname);
                if ($fileExists && ! $this->option('force')) {
                    $this->warn("skip {$relativeDest}");

                    continue;
                }

                $this->copyFile($filePathname, $destPathname, $destDirname);
                $this->info($fileExists ? "override {$relativeDest}" : "new {$relativeDest}");
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function getDistFiles(string $basePath): array
    {
        /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $distFiles */
        $distFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $basePath,
                \RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        /** @var array<int, string> $files */
        $files = array_map(
            static function (mixed $file): string {
                /** @var \SplFileInfo $file */
                return $file->getPathname();
            },
            iterator_to_array($distFiles)
        );

        sort($files);

        return $files;
    }

    protected function copyFile(string $filePathname, string $destPathname, string $destDirname): bool
    {
        if (! is_dir($destDirname)) {
            if (! mkdir($destDirname, 0755, true)) {
                throw new \Exception("Failed to create directory: {$destDirname}");
            }
        }

        $contents = file_get_contents((string) $filePathname);
        if ($contents === false) {
            throw new \Exception("Failed to read file: {$filePathname}");
        }

        if (file_put_contents($destPathname, $contents) === false) {
            throw new \Exception("Failed to write file: {$destPathname}");
        }

        return true;
    }
}
