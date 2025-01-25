<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;

class OmakaseCommand extends Command
{
    protected $signature = 'laravel:omakase
        {--composer : Install only composer packages}
        {--npm : Install only npm packages}
        {--files : Only copy files}';

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
                    'laravel/horizon' => [
                        ['php', 'artisan', 'horizon:install'],
                    ],
                    'laravel/pulse' => [
                        ['php', 'artisan', 'vendor:publish', '--tag=pulse-config'],
                        ['php', 'artisan', 'vendor:publish', '--tag=pulse-dashboard'],
                        ['php', 'artisan', 'vendor:publish', '--provider="Laravel\Pulse\PulseServiceProvider"'],
                        ['php', 'artisan', 'migrate'],
                    ],
                    'livewire/livewire' => [
                        ['php', 'artisan', 'livewire:publish', '--config'],
                    ],
                ],
                'require-dev' => [
                    'barryvdh/laravel-ide-helper',
                    'larastan/larastan',
                    'laravel/pint',
                    'pestphp/pest',
                ],
            ];

            if (! $this->installPackages($composerPackages, ['composer', 'require'], 'require-dev')) {
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

            if (! $this->installPackages($npmPackages, ['npm', 'install'], 'devDependencies')) {
                return self::FAILURE;
            }
        }

        if ($runAll || $runFiles) {
            $this->newLine();
            $this->line('╔═══════════════════════════════════════════╗');
            $this->line('║              Copying files                ║');
            $this->line('╚═══════════════════════════════════════════╝');
            $this->newLine();

            $basePath = __DIR__.'/../../files/';

            /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $files */
            $files = new \RecursiveIteratorIterator(
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
                iterator_to_array($files)
            );

            sort($files);

            foreach ($files as $filePathname) {
                $destPathname = base_path(explode($basePath, (string) $filePathname)[1]);
                $destDirname = dirname($destPathname);
                $relativeDest = str_replace(base_path().'/', '', $destPathname);

                if (file_exists($destPathname)) {
                    $this->warn("skip {$relativeDest}");

                    continue;
                }

                if (! is_dir($destDirname)) {
                    mkdir($destDirname, 0755, true);
                }

                $contents = file_get_contents((string) $filePathname);
                file_put_contents($destPathname, $contents);

                $this->info("new {$relativeDest}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string|array<array<string>>>>  $packages
     * @param  array<string>  $command
     */
    protected function installPackages(array $packages, array $command, string $devFlag = ''): bool
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
                $baseCommand[] = '--dev';
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
        if (PHP_OS_FAMILY !== 'Windows') {
            $process->setTty(true);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to run command');
            $this->error($process->getErrorOutput());

            return false;
        }

        return true;
    }
}
