<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class OmakaseCommand extends Command
{
    protected $signature = 'laravel:omakase
        {--files : Only copy files}
        {--composer : Install only composer packages}
        {--npm : Install only npm packages}
        {--force : Override existing files when copying}';

    protected $description = 'An opinionated menu for your next Laravel project';

    public function handle(): int
    {
        $runFiles = $this->option('files');
        $runComposer = $this->option('composer');
        $runNpm = $this->option('npm');

        // If no specific options are provided, run everything
        $runAll = ! $runFiles && ! $runComposer && ! $runNpm;

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

        if ($runAll || $runComposer) {
            $this->newLine();
            $this->line('╔═══════════════════════════════════════════╗');
            $this->line('║       Installing Composer Packages        ║');
            $this->line('╚═══════════════════════════════════════════╝');
            $this->newLine();

            $composerPackages = [
                'require' => [
                    'livewire/livewire' => [
                        'commands' => [
                            ['php', 'artisan', 'livewire:publish', '--config'],
                        ],
                    ],
                    'livewire/flux',
                    'spatie/laravel-data' => [
                        'commands' => [
                            ['php', 'artisan', 'vendor:publish', '--provider=Spatie\LaravelData\LaravelDataServiceProvider', '--tag=data-config'],
                        ],
                    ],
                ],
                'require-dev' => [
                    'barryvdh/laravel-ide-helper' => [
                        'commands' => [
                            ['php', 'artisan', 'ide-helper:generate'],
                            ['php', 'artisan', 'ide-helper:meta'],
                            ['php', 'artisan', 'ide-helper:models', '--nowrite'],
                        ],
                    ],
                    'rector/rector' => [
                        'optional_commands' => [
                            ['vendor/bin/rector'],
                        ],
                    ],
                    'laravel/pint' => [
                        'optional_commands' => [
                            ['vendor/bin/pint', '--repair'],
                        ],
                    ],
                    'larastan/larastan' => [
                        'optional_commands' => [
                            ['vendor/bin/phpstan', 'analyse'],
                        ],
                    ],
                    'pestphp/pest',
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
                    'tailwindcss',
                    '@tailwindcss/vite',
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

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string|array<string, array<array<string>>>>>  $packages
     * @param  array<string>  $command
     */
    protected function installPackages(array $packages, array $command, string $devFlag = '', string $devFlagValue = '--dev'): bool
    {
        foreach ($packages as $type => $typePackages) {
            $commands = [];
            $optionalCommands = [];
            $packageNames = [];

            /** @var string|array<string, array<array<string>>> $v */
            foreach ($typePackages as $k => $v) {
                if (is_string($v)) {
                    $packageNames[] = $v;
                } else {
                    $packageNames[] = (string) $k;
                    if (isset($v['commands'])) {
                        $commands = [...$commands, ...$v['commands']];
                    }
                    if (isset($v['optional_commands'])) {
                        $optionalCommands = [...$optionalCommands, ...$v['optional_commands']];
                    }
                }
            }

            $baseCommand = $command;
            if ($type === $devFlag) {
                $baseCommand[] = $devFlagValue;
            }

            $allCommands = [[...$baseCommand, ...$packageNames], ...$commands];
            if (! $this->execCommands($allCommands)) {
                return false;
            }

            // Execute optional commands that don't fail the installation
            if (! empty($optionalCommands)) {
                $this->execOptionalCommands($optionalCommands);
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
     * @param  array<array<string>>  $commands
     */
    protected function execOptionalCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->warn(implode(' ', $command));
            if (! $this->exec($command, optional: true)) {
                $this->comment('Optional command failed but continuing installation...');
            }
        }
    }

    /**
     * @param  array<string>  $command
     */
    protected function exec(array $command, bool $optional = false): bool
    {
        // Use Laravel's Process facade which can be faked in tests
        $process = Process::command($command);

        // Only use TTY mode if not running tests and not on Windows
        if (! app()->runningUnitTests() && PHP_OS_FAMILY !== 'Windows') {
            $process->tty();
        }

        $result = $process->run();

        if (! $result->successful()) {
            if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
                if ($optional) {
                    $this->comment('Optional command failed: '.implode(' ', $command));
                    if ($result->errorOutput()) {
                        $this->comment($result->errorOutput());
                    }
                } else {
                    $this->error('Failed to run command');
                    $this->error($result->errorOutput());
                }
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

                if (File::exists($destPathname) && ! $this->option('force')) {
                    $this->warn("skip {$relativeDest}");

                    continue;
                }

                $this->copyFile($filePathname, $destPathname, $destDirname);
                $this->info(File::exists($destPathname) ? "override {$relativeDest}" : "new {$relativeDest}");
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

        if (! file_put_contents($destPathname, $contents)) {
            throw new \Exception("Failed to write file: {$destPathname}");
        }

        return true;
    }
}
