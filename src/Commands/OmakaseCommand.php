<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

//
// Omakase Command - Laravel Package Installation & Configuration
// -------------------------------------------------------------------------------
//
// This command provides an opinionated selection of packages and configurations
// for Laravel projects. It handles Composer packages, NPM packages, and copies
// predefined configuration files.

class OmakaseCommand extends Command
{
    //
    // Command Definition
    // -------------------------------------------------------------------------------

    protected $signature = 'laravel:omakase
        {--files : Only copy files}
        {--composer : Install only composer packages}
        {--npm : Install only npm packages}
        {--skip-composer-json : Skip modifying composer.json}
        {--force : Override existing files and skip confirmation prompts}';

    protected $description = 'An opinionated menu for your next Laravel project';

    //
    // Main Entry Point
    // -------------------------------------------------------------------------------

    public function handle(): int
    {
        $runFiles = $this->option('files');
        $runComposer = $this->option('composer');
        $runNpm = $this->option('npm');

        // If no specific options are provided, run everything
        $runAll = ! $runFiles && ! $runComposer && ! $runNpm;

        //
        // File Operations

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

        //
        // Composer Package Installation

        if ($runAll || $runComposer) {
            $this->newLine();
            $this->line('╔═══════════════════════════════════════════╗');
            $this->line('║       Installing Composer Packages        ║');
            $this->line('╚═══════════════════════════════════════════╝');
            $this->newLine();

            // Ask if user wants to update existing packages first
            if ($this->option('force') || $this->confirm('Do you want to update existing Composer packages first?', false)) {
                $this->warn('composer update');
                if (! $this->exec(['composer', 'update'])) {
                    return self::FAILURE;
                }
                $this->newLine();
            }

            $composerPackages = config('laravel-omakase.composer-packages');

            if (! is_array($composerPackages)) {
                $this->error('Invalid composer packages configuration');

                return self::FAILURE;
            }

            /** @var array<string, array<string|array<string, array<array<string>>|array<string, mixed>>>> $composerPackages */
            if (! $this->installPackages($composerPackages, ['composer', 'require'], 'require-dev', '--dev')) {
                return self::FAILURE;
            }
        }

        //
        // NPM Package Installation

        if ($runAll || $runNpm) {
            $this->newLine();
            $this->line('╔═══════════════════════════════════════════╗');
            $this->line('║         Installing NPM Packages           ║');
            $this->line('╚═══════════════════════════════════════════╝');
            $this->newLine();

            // Ask if user wants to update existing packages first
            if ($this->option('force') || $this->confirm('Do you want to update existing NPM packages first?', false)) {
                $this->warn('npm update');
                if (! $this->exec(['npm', 'update'])) {
                    return self::FAILURE;
                }
                $this->newLine();
            }

            $npmPackages = config('laravel-omakase.npm-packages');

            if (! is_array($npmPackages)) {
                $this->error('Invalid npm packages configuration');

                return self::FAILURE;
            }

            /** @var array<string, array<string|array<string, array<array<string>>|array<string, mixed>>>> $npmPackages */
            if (! $this->installPackages($npmPackages, ['npm', 'install'], 'devDependencies', '--save-dev')) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    //
    // Package Installation
    // -------------------------------------------------------------------------------

    /**
     * Install packages and handle their configurations
     *
     * @param  array<string, array<string|array<string, array<array<string>>|array<string, mixed>>>>  $packages
     * @param  array<string>  $command
     */
    protected function installPackages(array $packages, array $command, string $devFlag = '', string $devFlagValue = '--dev'): bool
    {
        foreach ($packages as $type => $typePackages) {
            $commands = [];
            $optionalCommands = [];
            $packageNames = [];

            //
            // Package Processing

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

            //
            // Command Execution

            $baseCommand = $command;
            if ($type === $devFlag) {
                $baseCommand[] = $devFlagValue;
            }

            $allCommands = [[...$baseCommand, ...$packageNames], ...$commands];
            if (! $this->execCommands($allCommands)) {
                return false;
            }

            //
            // Composer.json Updates

            // Handle composer.json updates for specific packages
            /** @var string|array<string, array<array<string>>|array<string, mixed>> $v */
            foreach ($typePackages as $k => $v) {
                if (is_array($v) && isset($v['composer']) && ! $this->option('skip-composer-json')) {
                    $packageName = (string) $k;
                    $composerConfig = $v['composer'];

                    // Generate dynamic confirmation message based on what sections are being updated
                    $sections = array_keys($composerConfig);
                    $sectionNames = implode(', ', $sections);
                    $confirmMessage = "Add {$packageName} configuration to composer.json ({$sectionNames})?";

                    if ($this->confirm($confirmMessage, true)) {
                        if (! $this->updateComposerJson($composerConfig, $packageName)) {
                            $this->warn("Failed to update composer.json for {$packageName}, continuing...");
                        }
                    }
                }
            }

            //
            // Optional Commands

            // Execute optional commands that don't fail the installation
            if (! empty($optionalCommands)) {
                $this->execOptionalCommands($optionalCommands);
            }
        }

        return true;
    }

    //
    // External Process Execution
    // -------------------------------------------------------------------------------

    /**
     * Execute multiple commands in sequence
     *
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
     * Execute optional commands that don't fail the installation
     *
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
     * Execute a single command using Laravel's Process facade
     *
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

    //
    // File Operations
    // -------------------------------------------------------------------------------

    /**
     * Copy all files from the dist directory to the project
     */
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
     * Get all files from the dist directory recursively
     *
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

    /**
     * Copy a single file to its destination
     */
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

    //
    // Composer JSON Management
    // -------------------------------------------------------------------------------

    /**
     * Update composer.json with package-specific configuration
     *
     * @param  array<mixed>  $composerConfig
     */
    protected function updateComposerJson(array $composerConfig, string $packageName = ''): bool
    {
        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            $this->error('composer.json not found');

            return false;
        }

        try {
            // Read and parse composer.json
            $composerContent = File::get($composerPath);
            $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($composerData)) {
                $this->error('Invalid composer.json structure');

                return false;
            }

            $sectionsUpdated = [];

            // Process each section in the composer configuration
            foreach ($composerConfig as $section => $sectionData) {
                if (! is_array($sectionData)) {
                    continue;
                }

                $updated = $this->updateComposerSection($composerData, $section, $sectionData, $packageName);
                if ($updated) {
                    $sectionsUpdated[] = $section;
                }
            }

            if (empty($sectionsUpdated)) {
                $this->comment('No changes needed for composer.json');

                return true;
            }

            // Write back to composer.json with proper formatting
            $formattedJson = json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($formattedJson === false) {
                $this->error('Failed to encode composer.json data');

                return false;
            }

            File::put($composerPath, $formattedJson."\n");

            $sectionList = implode(', ', $sectionsUpdated);
            $this->info("Updated composer.json sections: {$sectionList}");

            return true;

        } catch (\JsonException $e) {
            $this->error('Invalid JSON in composer.json: '.$e->getMessage());

            return false;
        } catch (\Exception $e) {
            $this->error('Failed to update composer.json: '.$e->getMessage());

            return false;
        }
    }

    //
    // Section-Specific Updates

    /**
     * Update a specific section of composer.json
     *
     * @param  array<mixed>  $composerData
     * @param  array<mixed>  $sectionData
     */
    protected function updateComposerSection(array &$composerData, string $section, array $sectionData, string $packageName): bool
    {
        switch ($section) {
            case 'scripts':
                return $this->updateComposerScripts($composerData, $sectionData, $packageName);
            case 'repositories':
                return $this->updateComposerRepositories($composerData, $sectionData, $packageName);
            case 'config':
            case 'extra':
                return $this->updateComposerObjectSection($composerData, $section, $sectionData, $packageName);
            default:
                $this->warn("Unknown composer.json section: {$section}");

                return false;
        }
    }

    /**
     * Update composer scripts section
     *
     * @param  array<mixed>  $composerData
     * @param  array<mixed>  $scriptsData
     */
    protected function updateComposerScripts(array &$composerData, array $scriptsData, string $packageName): bool
    {
        // Initialize scripts section if it doesn't exist
        if (! isset($composerData['scripts']) || ! is_array($composerData['scripts'])) {
            $composerData['scripts'] = [];
        }

        $updated = false;

        foreach ($scriptsData as $scriptName => $commands) {
            if (! is_array($commands)) {
                continue;
            }

            if (! isset($composerData['scripts'][$scriptName])) {
                // Script doesn't exist, create it
                $composerData['scripts'][$scriptName] = $commands;
                $this->info("Added {$scriptName} scripts to composer.json");
                $updated = true;
            } else {
                // Script exists, merge commands
                $existingCommands = $composerData['scripts'][$scriptName];

                // Convert string to array if needed
                if (is_string($existingCommands)) {
                    $existingCommands = [$existingCommands];
                }

                if (! is_array($existingCommands)) {
                    $this->error("Invalid {$scriptName} structure in composer.json");

                    continue;
                }

                $commandsAdded = [];
                foreach ($commands as $command) {
                    if (is_string($command) && ! $this->commandExists($existingCommands, $command)) {
                        $existingCommands[] = $command;
                        $commandsAdded[] = $command;
                    }
                }

                if (! empty($commandsAdded)) {
                    $composerData['scripts'][$scriptName] = $existingCommands;
                    $this->info('Added '.count($commandsAdded)." new script(s) to {$scriptName}");
                    foreach ($commandsAdded as $cmd) {
                        $this->line("  + {$cmd}");
                    }
                    $updated = true;
                } else {
                    $this->comment("All required scripts already exist in {$scriptName}");
                }
            }
        }

        return $updated;
    }

    /**
     * Update composer repositories section
     *
     * @param  array<mixed>  $composerData
     * @param  array<mixed>  $repositoriesData
     */
    protected function updateComposerRepositories(array &$composerData, array $repositoriesData, string $packageName): bool
    {
        // Initialize repositories section if it doesn't exist
        if (! isset($composerData['repositories']) || ! is_array($composerData['repositories'])) {
            $composerData['repositories'] = [];
        }

        $updated = false;

        foreach ($repositoriesData as $repository) {
            if (! is_array($repository) || ! isset($repository['url'])) {
                continue;
            }

            $exists = array_any($composerData['repositories'], fn ($existingRepo) => is_array($existingRepo) && isset($existingRepo['url']) && $existingRepo['url'] === $repository['url']);

            if (! $exists) {
                $composerData['repositories'][] = $repository;
                $url = is_string($repository['url']) ? $repository['url'] : 'unknown';
                $this->info("Added repository: {$url}");
                $updated = true;
            } else {
                $url = is_string($repository['url']) ? $repository['url'] : 'unknown';
                $this->comment("Repository already exists: {$url}");
            }
        }

        return $updated;
    }

    /**
     * Update composer object sections (config, extra, etc.)
     *
     * @param  array<mixed>  $composerData
     * @param  array<mixed>  $sectionData
     */
    protected function updateComposerObjectSection(array &$composerData, string $section, array $sectionData, string $packageName): bool
    {
        // Initialize section if it doesn't exist
        if (! isset($composerData[$section]) || ! is_array($composerData[$section])) {
            $composerData[$section] = [];
        }

        // Deep replace the section data (replace values instead of merging arrays)
        $originalData = $composerData[$section];
        $composerData[$section] = array_replace_recursive($composerData[$section], $sectionData);

        // Check if anything actually changed
        $updated = $originalData !== $composerData[$section];

        if ($updated) {
            $this->info("Updated {$section} section in composer.json");
        } else {
            $this->comment("No changes needed for {$section} section");
        }

        return $updated;
    }

    //
    // Utility Methods

    /**
     * Normalize command string for comparison
     */
    protected function normalizeCommand(string $command): string
    {
        $normalized = preg_replace('/\s+/', ' ', $command);

        if ($normalized === null) {
            $this->comment("Failed to normalize command: {$command}");

            return trim($command);
        }

        return trim($normalized);
    }

    /**
     * Check if a command already exists in the commands array
     *
     * @param  array<int|string, mixed>  $commands
     */
    protected function commandExists(array $commands, string $targetCommand): bool
    {
        $normalizedTarget = $this->normalizeCommand($targetCommand);
        foreach ($commands as $command) {
            if (is_string($command) && $this->normalizeCommand($command) === $normalizedTarget) {
                return true;
            }
        }

        return false;
    }
}
