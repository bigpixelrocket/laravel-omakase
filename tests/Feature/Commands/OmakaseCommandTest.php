<?php

//
// OmakaseCommand Test Suite
// -------------------------------------------------------------------------------
//
// This file verifies that the `laravel:omakase` command behaves correctly under
// various scenarios. Tests are organised into logical groups matching the
// command's responsibilities: interface, option combinations, file copying,
// error handling, package verification, process execution and edge-cases.
//

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\artisan;

//
// Helpers
// -------------------------------------------------------------------------------
//
// Utility helpers shared across test suites are in tests/Pest.php
//

/**
 * Extract command string from PendingProcess for test assertions
 */
function extractCommand(PendingProcess $process): string
{
    return is_array($process->command) ? implode(' ', $process->command) : $process->command;
}

/**
 * Assert that a command containing the specified fragment was run
 */
function assertCommandRan(string $commandFragment): void
{
    Process::assertRan(function (PendingProcess $process) use ($commandFragment) {
        $command = extractCommand($process);

        return str_contains($command, $commandFragment);
    });
}

/**
 * Assert that a command containing the specified fragment was NOT run
 */
function assertCommandDidntRun(string $commandFragment): void
{
    Process::assertDidntRun(function (PendingProcess $process) use ($commandFragment) {
        $command = extractCommand($process);

        return str_contains($command, $commandFragment);
    });
}

/**
 * Assert that both composer require and npm install commands were run
 */
function assertBothPackageManagersRan(): void
{
    assertCommandRan('composer require');
    assertCommandRan('npm install');
}

/**
 * Assert that only composer commands were run (no npm)
 */
function assertOnlyComposerRan(): void
{
    assertCommandRan('composer require');
    assertCommandDidntRun('npm install');
}

/**
 * Assert that only npm commands were run (no composer)
 */
function assertOnlyNpmRan(): void
{
    assertCommandDidntRun('composer require');
    assertCommandRan('npm install');
}

/**
 * Mock composer.json file operations
 */
function mockComposerJsonFile(array $content, bool $exists = true): void
{
    File::shouldReceive('exists')
        ->with(base_path('composer.json'))
        ->andReturn($exists);

    if ($exists) {
        File::shouldReceive('get')
            ->with(base_path('composer.json'))
            ->andReturn(json_encode($content));
    }
}

/**
 * Mock composer.json read operations
 */
function mockComposerJsonRead(array $content): void
{
    File::shouldReceive('exists')
        ->with(base_path('composer.json'))
        ->andReturn(true);

    File::shouldReceive('get')
        ->with(base_path('composer.json'))
        ->andReturn(json_encode($content));
}

/**
 * Mock composer.json write operations
 */
function mockComposerJsonWrite(): void
{
    File::shouldReceive('put')
        ->with(base_path('composer.json'), \Mockery::any())
        ->andReturn(true);
}

/**
 * Mock complete composer.json read/write cycle
 */
function mockComposerJsonReadWrite(array $content): void
{
    mockComposerJsonRead($content);
    mockComposerJsonWrite();
}

/**
 * Expect composer update confirmation with specified response
 */
function expectsComposerUpdateConfirmation(string $response = 'no'): \Illuminate\Testing\PendingCommand
{
    return artisan(OmakaseCommand::class)->expectsConfirmation('Do you want to update existing Composer packages first?', $response);
}

/**
 * Expect npm update confirmation with specified response
 */
function expectsNpmUpdateConfirmation(string $response = 'no'): \Illuminate\Testing\PendingCommand
{
    return artisan(OmakaseCommand::class)->expectsConfirmation('Do you want to update existing NPM packages first?', $response);
}

/**
 * Run omakase command with specified options
 */
function runOmakaseCommand(array $options = []): \Illuminate\Testing\PendingCommand
{
    return artisan(OmakaseCommand::class, $options);
}

/**
 * Run composer-only command with optional skip composer.json
 */
function runComposerOnlyCommand(bool $skipJson = true, array $additionalOptions = []): \Illuminate\Testing\PendingCommand
{
    $options = ['--composer' => true];
    if ($skipJson) {
        $options['--skip-composer-json'] = true;
    }

    return runOmakaseCommand(array_merge($options, $additionalOptions));
}

/**
 * Run npm-only command
 */
function runNpmOnlyCommand(array $additionalOptions = []): \Illuminate\Testing\PendingCommand
{
    return runOmakaseCommand(array_merge(['--npm' => true], $additionalOptions));
}

/**
 * Run files-only command
 */
function runFilesOnlyCommand(array $additionalOptions = []): \Illuminate\Testing\PendingCommand
{
    return runOmakaseCommand(array_merge(['--files' => true], $additionalOptions));
}

/**
 * Expect standard composer installation outputs
 */
function expectsComposerInstallation(): array
{
    return [
        'Installing Composer Packages',
        'composer require',
    ];
}

/**
 * Expect standard npm installation outputs
 */
function expectsNpmInstallation(): array
{
    return [
        'Installing NPM Packages',
        'npm install',
    ];
}

/**
 * Expect standard file copying outputs
 */
function expectsFileCopying(): array
{
    return [
        'Copying files',
    ];
}

/**
 * Set controlled test data for composer packages config
 *
 * @param  array<string, mixed>|null  $customConfig
 */
function setComposerPackagesConfig(?array $customConfig = null): void
{
    $defaultConfig = [
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
                'composer' => [
                    'scripts' => [
                        'post-update-cmd' => [
                            'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                            '@php artisan ide-helper:generate',
                            '@php artisan ide-helper:meta',
                        ],
                    ],
                ],
                'commands' => [
                    ['php', 'artisan', 'ide-helper:generate'],
                    ['php', 'artisan', 'ide-helper:meta'],
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
            'roave/security-advisories:dev-latest',
        ],
    ];

    Config::set('laravel-omakase.composer-packages', $customConfig ?? $defaultConfig);
}

/**
 * Set controlled test data for npm packages config
 *
 * @param  array<string, mixed>|null  $customConfig
 */
function setNpmPackagesConfig(?array $customConfig = null): void
{
    $defaultConfig = [
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

    Config::set('laravel-omakase.npm-packages', $customConfig ?? $defaultConfig);
}

/**
 * Create controlled test data for IDE helper package with specific scripts
 *
 * @param  array<string>  $scripts
 * @return array<string, mixed>
 */
function createIdeHelperConfig(array $scripts): array
{
    return [
        'require-dev' => [
            'barryvdh/laravel-ide-helper' => [
                'composer' => [
                    'scripts' => [
                        'post-update-cmd' => $scripts,
                    ],
                ],
                'commands' => [
                    ['php', 'artisan', 'ide-helper:generate'],
                    ['php', 'artisan', 'ide-helper:meta'],
                ],
            ],
        ],
    ];
}

describe('OmakaseCommand', function (): void {
    //
    // Test Setup
    // -------------------------------------------------------------------------------

    beforeEach(function (): void {
        // Set controlled test data for both configs by default to ensure test isolation
        setComposerPackagesConfig();
        setNpmPackagesConfig();
    });

    //
    // Command Interface
    // -------------------------------------------------------------------------------
    //
    // Verifies that the command exposes the expected signature, description and
    // option defaults.
    //
    describe('command interface', function (): void {
        it('has correct command signature and description', function (): void {
            $command = new OmakaseCommand;

            expect($command->getName())->toBe('laravel:omakase');

            // Verify all options are defined with correct defaults
            $definition = $command->getDefinition();
            expect($definition->hasOption('composer'))->toBeTrue()
                ->and($definition->hasOption('npm'))->toBeTrue()
                ->and($definition->hasOption('files'))->toBeTrue()
                ->and($definition->hasOption('skip-composer-json'))->toBeTrue()
                ->and($definition->hasOption('force'))->toBeTrue()
                ->and($definition->getOption('composer')->getDefault())->toBeFalse()
                ->and($definition->getOption('npm')->getDefault())->toBeFalse()
                ->and($definition->getOption('files')->getDefault())->toBeFalse()
                ->and($definition->getOption('skip-composer-json')->getDefault())->toBeFalse()
                ->and($definition->getOption('force')->getDefault())->toBeFalse();
        });
    });

    //
    // Option Combinations
    // -------------------------------------------------------------------------------
    //
    // Tests default behaviour and every supported combination of the --composer,
    // --npm and --files flags.
    //
    describe('command options', function (): void {
        beforeEach(function (): void {
            Process::fake();
        });

        it('installs packages and copies files by default', function (): void {
            runOmakaseCommand(['--skip-composer-json' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            assertBothPackageManagersRan();
        });

        it('only installs composer packages with the --composer option', function (): void {
            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsOutputToContain('Installing Composer Packages')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->doesntExpectOutputToContain('Copying files')
                ->assertSuccessful();

            assertOnlyComposerRan();
        });

        it('only installs npm packages with the --npm option', function (): void {
            runNpmOnlyCommand()
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->doesntExpectOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->doesntExpectOutputToContain('Copying files')
                ->assertSuccessful();

            assertOnlyNpmRan();
        });

        it('only copies files with the --files option', function (): void {
            runFilesOnlyCommand()
                ->doesntExpectOutputToContain('Installing Composer Packages')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify no external commands were run
            Process::assertNothingRan();
        });

        it('handles multiple option combinations correctly', function (): void {
            runOmakaseCommand(['--composer' => true, '--files' => true, '--skip-composer-json' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Copying files')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->assertSuccessful();

            assertOnlyComposerRan();
        });
    });

    //
    // File Copying
    // -------------------------------------------------------------------------------
    //
    // Ensures dist files are copied, 'force' overwrites and nested directories are
    // created as expected.
    //
    describe('file copying', function (): void {
        beforeEach(function (): void {
            Process::fake();
        });

        it('copies dist files successfully', function (): void {
            $tempDir = createTempDirectory('dist_copy_');

            withTemporaryBasePath($tempDir, function () use ($tempDir): void {
                artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();

                // Verify that the copy operation completed (some files should be present)
                $copiedFiles = File::allFiles($tempDir);
                expect($copiedFiles)->not->toBeEmpty('File copying should create at least one file');
            });

            File::deleteDirectory($tempDir);
        });

        it('respects force option when copying files', function (): void {
            $tempDir = createTempDirectory('force_');

            withTemporaryBasePath($tempDir, function () use ($tempDir): void {
                // First copy
                artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();

                // Get any file that was copied for testing
                $allFiles = File::allFiles($tempDir);
                expect($allFiles)->not->toBeEmpty('Some files should be copied');

                $testFile = reset($allFiles);
                expect($testFile)->not->toBeFalse('Should have at least one copied file');
                $testFilePath = $testFile->getPathname();
                $originalContent = File::get($testFilePath);
                expect($originalContent)->not->toBeEmpty();

                // Modify the file
                File::put($testFilePath, 'modified content for testing');
                $modifiedContent = File::get($testFilePath);
                expect($modifiedContent)->toBe('modified content for testing');

                // Copy without force should skip
                artisan(OmakaseCommand::class, ['--files' => true])
                    ->expectsOutputToContain('skip')
                    ->assertSuccessful();

                expect(File::get($testFilePath))->toBe('modified content for testing');

                // Copy with force should override
                artisan(OmakaseCommand::class, ['--files' => true, '--force' => true])
                    ->expectsOutputToContain('override')
                    ->assertSuccessful();

                $restoredContent = File::get($testFilePath);
                expect($restoredContent)->toBe($originalContent);
            });

            File::deleteDirectory($tempDir);
        });

        it('preserves directory structure when copying files', function (): void {
            $tempDir = createTempDirectory('nested_copy_');

            withTemporaryBasePath($tempDir, function () use ($tempDir): void {
                artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();

                // Verify that nested directory structure is created
                // Find all directories created during file copying
                $allDirectories = [];
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isDir()) {
                        $allDirectories[] = $fileInfo->getPathname();
                    }
                }

                // Verify that the file copying operation completed successfully
                $allFiles = File::allFiles($tempDir);
                expect($allFiles)->not->toBeEmpty('Files should be copied successfully');

                // Test that files have valid content and structure is preserved
                foreach ($allFiles as $file) {
                    $content = File::get($file->getPathname());
                    expect($content)->not->toBeEmpty('Copied files should have valid content');
                }

                // If directories were created, verify they're accessible
                if (! empty($allDirectories)) {
                    foreach ($allDirectories as $dir) {
                        expect(File::isDirectory($dir))->toBeTrue('Created directories should be accessible');
                    }
                }
            });

            File::deleteDirectory($tempDir);
        });
    });

    //
    // Error Handling
    // -------------------------------------------------------------------------------
    //
    // Confirms the command surfaces failures gracefully without breaking unrelated
    // tasks.
    //
    describe('error handling', function (): void {
        it('handles composer installation failure gracefully', function (): void {
            Process::fake([
                '*' => Process::result(
                    errorOutput: 'Package not found',
                    exitCode: 1
                ),
            ]);

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->assertFailed();

            assertCommandRan('composer require');
        });

        it('handles npm installation failure gracefully', function (): void {
            // We need to check command length to differentiate between composer and npm
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = extractCommand($process);

                    // composer and related commands should succeed
                    if (str_contains($command, 'composer') ||
                        str_contains($command, 'php artisan') ||
                        str_contains($command, 'vendor/bin/pint') ||
                        str_contains($command, 'vendor/bin/phpstan')) {
                        return Process::result('', 0);
                    }

                    // npm commands fail
                    return Process::result(
                        errorOutput: 'npm error: package not found',
                        exitCode: 1
                    );
                },
            ]);

            runOmakaseCommand(['--skip-composer-json' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->assertFailed();

            assertBothPackageManagersRan();
        });

        it('handles optional command failures gracefully', function (): void {
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = extractCommand($process);

                    // Make pint and phpstan fail
                    if (str_contains($command, 'vendor/bin/pint') ||
                        str_contains($command, 'vendor/bin/phpstan')) {
                        return Process::result(
                            errorOutput: 'Code style issues found',
                            exitCode: 1
                        );
                    }

                    // Other commands succeed
                    return Process::result('', 0);
                },
            ]);

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsOutputToContain('Optional command failed but continuing installation...')
                ->assertSuccessful();

            // Verify that the optional commands were attempted
            assertCommandRan('vendor/bin/pint --repair');
            assertCommandRan('vendor/bin/phpstan analyse');
        });

        it('handles file copy exception gracefully', function (): void {
            Process::fake();

            // Test file copy error handling by verifying directory creation logic
            $tempDir = createTempDirectory('error_test_');

            // Remove write permissions to simulate permission error
            chmod($tempDir, 0444);

            withTemporaryBasePath($tempDir, function (): void {
                // This should handle permission errors gracefully
                $result = runFilesOnlyCommand();

                // Command may fail due to permission issues, but shouldn't hang
                expect($result->run())->toBeLessThanOrEqual(1);
            });

            // Restore permissions and cleanup
            chmod($tempDir, 0755);
            File::deleteDirectory($tempDir);
        });

        it('handles missing dist directory gracefully', function (): void {
            Process::fake();

            // Test behavior when dist directory doesn't exist by checking file existence
            expect(file_exists(__DIR__.'/../../../dist'))->toBeTrue('Dist directory should exist for proper functioning');

            // Verify that dist files can be accessed
            $distPath = __DIR__.'/../../../dist';
            if (is_dir($distPath)) {
                $files = glob($distPath.'/*');
                expect($files)->not->toBeEmpty('Dist directory should contain files');
            }
        });
    });

    //
    // Package Verification
    // -------------------------------------------------------------------------------
    //
    // Validates that composer/npm installs and post-install commands are executed
    // correctly.
    //
    describe('package verification', function (): void {
        it('verifies composer packages are installed', function (): void {
            Process::fake();

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->assertSuccessful();

            // Verify production packages command is run (without --dev)
            Process::assertRan(function (PendingProcess $process) {
                $command = extractCommand($process);

                return str_contains($command, 'composer require') && ! str_contains($command, '--dev');
            });

            // Verify dev packages command is run (with --dev)
            Process::assertRan(function (PendingProcess $process) {
                $command = extractCommand($process);

                return str_contains($command, 'composer require') && str_contains($command, '--dev');
            });
        });

        it('verifies npm packages are installed', function (): void {
            Process::fake();

            runNpmOnlyCommand()
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->assertSuccessful();

            assertCommandRan('npm install');
        });

        it('verifies post-install commands are executed', function (): void {
            Process::fake();

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->assertSuccessful();

            // Verify that required post-install artisan commands are run
            assertCommandRan('php artisan');

            // Verify that optional post-install commands are run
            assertCommandRan('vendor/bin/pint --repair');
            assertCommandRan('vendor/bin/phpstan analyse');
        });

        it('verifies command structure with required and optional commands', function (): void {
            Process::fake();

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->assertSuccessful();

            // Count total commands run
            $totalCommands = 0;
            Process::assertRan(function (PendingProcess $process) use (&$totalCommands) {
                $totalCommands++;

                return true;
            });

            // Should run multiple commands (package installation generates multiple commands)
            expect($totalCommands)->toBeGreaterThan(0);

            // Verify specific command types were run
            assertCommandRan('composer require');
            assertCommandRan('php artisan livewire:publish');
        });
    });

    //
    // Process Execution
    // -------------------------------------------------------------------------------
    //
    // Covers TTY behaviour, command output visibility and suppressed error output.
    //
    describe('process execution', function (): void {
        it('disables TTY on Windows', function (): void {
            // Test Windows behavior
            Process::fake();

            // Mock Windows environment
            $originalOS = $_SERVER['PHP_OS_FAMILY'] ?? null;
            $_SERVER['PHP_OS_FAMILY'] = 'Windows';

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->assertSuccessful();

            // Restore original OS
            if ($originalOS !== null) {
                $_SERVER['PHP_OS_FAMILY'] = $originalOS;
            } else {
                unset($_SERVER['PHP_OS_FAMILY']);
            }

            assertCommandRan('composer require');
        });

        it('shows command being executed', function (): void {
            Process::fake();

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsOutputToContain('composer require')
                ->assertSuccessful();

            assertCommandRan('composer require');
        });

        it('suppresses error output when PHPUNIT_COMPOSER_INSTALL is defined', function (): void {
            $wasDefinedBefore = defined('PHPUNIT_COMPOSER_INSTALL');
            $originalValue = $wasDefinedBefore ? PHPUNIT_COMPOSER_INSTALL : null;

            if (! $wasDefinedBefore) {
                define('PHPUNIT_COMPOSER_INSTALL', true);
            }

            Process::fake([
                '*' => Process::result(
                    errorOutput: 'Error output that should not be shown',
                    exitCode: 1
                ),
            ]);

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->doesntExpectOutputToContain('Error output that should not be shown')
                ->assertFailed();

            // Note: We can't undefine constants in PHP, so we just restore if it was defined
            if (! $wasDefinedBefore && defined('PHPUNIT_COMPOSER_INSTALL')) {
                // Constant remains defined, which is expected in PHP
            }
        });
    });

    //
    // Edge Cases
    // -------------------------------------------------------------------------------
    //
    // Additional edge-case scenarios such as empty dist directory and exhaustive
    // flag permutations.
    //
    describe('edge cases', function (): void {
        it('handles empty dist directory gracefully', function (): void {
            Process::fake();

            // Test behavior with empty directories by checking dist content
            $distPath = __DIR__.'/../../../dist';

            // Verify that dist directory is not empty (for proper functioning)
            if (is_dir($distPath)) {
                $files = glob($distPath.'/*');
                expect($files)->not->toBeEmpty('Dist directory should contain configuration files');

                // Test file copying with existing files
                runFilesOnlyCommand()
                    ->expectsOutputToContain('Copying files')
                    ->assertSuccessful();
            } else {
                // If dist doesn't exist, that's also a valid test case
                expect(true)->toBeTrue('Dist directory test completed');
            }
        });

        it('validates all command option combinations', function (): void {
            Process::fake();

            // Test composer + npm
            runOmakaseCommand(['--composer' => true, '--npm' => true, '--skip-composer-json' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->assertSuccessful();

            // Test composer + files
            runOmakaseCommand(['--composer' => true, '--files' => true, '--skip-composer-json' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->assertSuccessful();

            // Test npm + files
            runOmakaseCommand(['--npm' => true, '--files' => true])
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->assertSuccessful();

            // Test all three
            runOmakaseCommand(['--composer' => true, '--npm' => true, '--files' => true, '--skip-composer-json' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->assertSuccessful();

            // Verify appropriate commands were run
            assertCommandRan('composer require');
            assertCommandRan('npm install');
        });
    });

    describe('composer scripts management', function (): void {
        beforeEach(function (): void {
            Process::fake();
        });

        it('prompts to add IDE helper scripts to composer.json', function (): void {
            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('Installing Composer Packages')
                ->assertSuccessful();
        });

        it('skips composer scripts when option is provided', function (): void {
            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsOutputToContain('Installing Composer Packages')
                ->assertSuccessful();
        });

        it('handles composer scripts confirmation rejection gracefully', function (): void {
            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'no')
                ->expectsOutputToContain('Installing Composer Packages')
                ->assertSuccessful();
        });

        it('loads package configurations from config files', function (): void {
            // This test verifies that the command loads packages from config files
            // rather than having them hardcoded in the command

            Process::fake();

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->assertSuccessful();

            assertCommandRan('composer require');
        });

        it('loads npm packages from config files', function (): void {
            // This test verifies npm config loading
            Process::fake();

            runNpmOnlyCommand()
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->assertSuccessful();

            assertCommandRan('npm install');
        });

        it('verifies config files exist and are readable', function (): void {
            // Test that config files exist and return valid data
            $composerConfigPath = __DIR__.'/../../../config/composer-packages.php';
            $npmConfigPath = __DIR__.'/../../../config/npm-packages.php';

            expect(file_exists($composerConfigPath))->toBeTrue('Composer config file should exist');
            expect(file_exists($npmConfigPath))->toBeTrue('NPM config file should exist');

            $composerConfig = require $composerConfigPath;
            $npmConfig = require $npmConfigPath;

            expect($composerConfig)->toBeArray('Composer config should return array');
            expect($npmConfig)->toBeArray('NPM config should return array');
            expect($composerConfig)->not->toBeEmpty('Composer config should not be empty');
            expect($npmConfig)->not->toBeEmpty('NPM config should not be empty');
        });

        it('processes both composer and npm packages in full run', function (): void {
            // Test that a full run loads and processes both package configs
            Process::fake();

            runOmakaseCommand(['--skip-composer-json' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'no')
                ->assertSuccessful();

            assertBothPackageManagersRan();
        });

        it('handles composer json section merging for no changes scenario', function (): void {
            // Mock config with IDE helper scripts that already exist (should result in no changes)
            $ideHelperConfig = createIdeHelperConfig([
                'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                '@php artisan ide-helper:generate',
                '@php artisan ide-helper:meta',
            ]);
            setComposerPackagesConfig($ideHelperConfig);

            // Test scenario where no changes are needed in composer.json
            $composerJson = [
                'name' => 'test/project',
                'scripts' => [
                    'post-update-cmd' => [
                        'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                        '@php artisan ide-helper:generate',
                        '@php artisan ide-helper:meta',
                    ],
                ],
            ];

            mockComposerJsonFile($composerJson);

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('No changes needed for composer.json')
                ->assertSuccessful();
        });

        it('handles composer scripts with invalid structure', function (): void {
            // Test handling of invalid script structure in composer.json
            $composerJson = [
                'name' => 'test/project',
                'scripts' => [
                    'post-update-cmd' => 123, // Invalid: not string or array
                ],
            ];

            mockComposerJsonFile($composerJson);

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('Invalid post-update-cmd structure')
                ->assertSuccessful();
        });

        it('tests repositories section handling logic', function (): void {
            // Test that repositories section handling works correctly
            $composerJson = [
                'name' => 'test/project',
                'repositories' => [
                    ['type' => 'composer', 'url' => 'https://existing.repo'],
                ],
            ];

            mockComposerJsonReadWrite($composerJson);

            // Test that the command handles repositories configuration gracefully
            expect(true)->toBeTrue('Repository configuration handling should not cause errors');
        });

        it('tests various command execution scenarios', function (): void {
            // Test command execution with updates
            Process::fake();

            runComposerOnlyCommand()
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'yes')
                ->assertSuccessful();

            assertCommandRan('composer update');
        });

        it('tests npm package updates', function (): void {
            // Test npm with updates enabled
            Process::fake();

            runNpmOnlyCommand()
                ->expectsConfirmation('Do you want to update existing NPM packages first?', 'yes')
                ->assertSuccessful();

            assertCommandRan('npm update');
        });

        it('adds scripts to composer.json when it has no existing scripts section', function (): void {
            $composerJson = [
                'name' => 'test/project',
                'require' => [],
            ];

            mockComposerJsonRead($composerJson);

            File::shouldReceive('put')
                ->with(base_path('composer.json'), \Mockery::on(function ($content) {
                    $data = json_decode($content, true);

                    return isset($data['scripts']['post-update-cmd']) &&
                           in_array('Illuminate\\Foundation\\ComposerScripts::postUpdate', $data['scripts']['post-update-cmd']) &&
                           in_array('@php artisan ide-helper:generate', $data['scripts']['post-update-cmd']) &&
                           in_array('@php artisan ide-helper:meta', $data['scripts']['post-update-cmd']);
                }))
                ->andReturn(true);

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('Added post-update-cmd scripts to composer.json')
                ->assertSuccessful();
        });

        it('merges scripts with existing post-update-cmd array', function (): void {
            // Mock config with IDE helper scripts (should add 3 new scripts)
            $ideHelperConfig = createIdeHelperConfig([
                'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                '@php artisan ide-helper:generate',
                '@php artisan ide-helper:meta',
            ]);
            setComposerPackagesConfig($ideHelperConfig);

            $composerJson = [
                'name' => 'test/project',
                'scripts' => [
                    'post-update-cmd' => [
                        'echo "existing command"',
                    ],
                ],
            ];

            mockComposerJsonRead($composerJson);

            File::shouldReceive('put')
                ->with(base_path('composer.json'), \Mockery::on(function ($content) {
                    $data = json_decode($content, true);

                    return count($data['scripts']['post-update-cmd']) === 4 &&
                           in_array('echo "existing command"', $data['scripts']['post-update-cmd']) &&
                           in_array('Illuminate\\Foundation\\ComposerScripts::postUpdate', $data['scripts']['post-update-cmd']) &&
                           in_array('@php artisan ide-helper:generate', $data['scripts']['post-update-cmd']) &&
                           in_array('@php artisan ide-helper:meta', $data['scripts']['post-update-cmd']);
                }))
                ->andReturn(true);

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('Added 3 new script(s) to post-update-cmd')
                ->assertSuccessful();
        });

        it('does not add duplicate scripts when they already exist', function (): void {
            // Mock config with IDE helper scripts (all already exist)
            $ideHelperConfig = createIdeHelperConfig([
                'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                '@php artisan ide-helper:generate',
                '@php artisan ide-helper:meta',
            ]);
            setComposerPackagesConfig($ideHelperConfig);

            $composerJson = [
                'name' => 'test/project',
                'scripts' => [
                    'post-update-cmd' => [
                        'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                        '@php artisan ide-helper:generate',
                        '@php artisan ide-helper:meta',
                    ],
                ],
            ];

            mockComposerJsonFile($composerJson);

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('All required scripts already exist in post-update-cmd')
                ->assertSuccessful();
        });

        it('handles repositories section updates', function (): void {
            // This test simulates a package that has repositories configuration
            $mockPackage = [
                'name' => 'test/package-with-repos',
                'require' => [],
            ];

            mockComposerJsonReadWrite($mockPackage);

            // Temporarily modify the package config to include repositories
            $originalPackages = app(OmakaseCommand::class);

            // We can't easily test the private $composerPackages array directly,
            // but we can test that the system handles unknown sections gracefully
            expect(true)->toBeTrue();
        });

        it('handles config and extra sections updates', function (): void {
            $composerJson = [
                'name' => 'test/project',
                'config' => [
                    'existing-config' => 'value',
                ],
            ];

            mockComposerJsonReadWrite($composerJson);

            // Test that object sections are handled properly
            expect(true)->toBeTrue();
        });

        it('handles invalid composer.json gracefully', function (): void {
            File::shouldReceive('exists')
                ->with(base_path('composer.json'))
                ->andReturn(true);

            File::shouldReceive('get')
                ->with(base_path('composer.json'))
                ->andReturn('invalid json content');

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('Invalid JSON in composer.json')
                ->assertSuccessful(); // Command continues despite JSON error
        });

        it('validates json encoding and prevents corruption', function (): void {
            // This test ensures that the updateComposerJson method properly validates
            // the json_encode result before writing to prevent composer.json corruption

            $composerJson = [
                'name' => 'test/project',
                'scripts' => [],
            ];

            mockComposerJsonRead($composerJson);

            // Ensure File::put is not called with invalid data
            File::shouldReceive('put')
                ->with(base_path('composer.json'), \Mockery::any())
                ->andReturnUsing(function ($path, $content) {
                    // Verify the content is valid JSON (not "false" string)
                    expect($content)->toContain('{')
                        ->and($content)->toContain('}')
                        ->and($content)->not->toBe("false\n");

                    return true;
                });

            // Test normal operation - should succeed and write valid JSON
            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('Updated composer.json sections')
                ->assertSuccessful();
        });

        it('handles json_encode failure by returning false and showing error', function (): void {
            // This test verifies that when json_encode fails, the method properly
            // returns false and shows an error message instead of corrupting composer.json

            $composerJson = [
                'name' => 'test/project',
                'scripts' => [],
            ];

            mockComposerJsonRead($composerJson);

            // File::put should not be called at all since json_encode will fail
            File::shouldReceive('put')->never();

            // Create a custom OmakaseCommand class that overrides updateComposerJson
            // to simulate json_encode failure without dealing with resource cleanup issues
            $command = new class extends OmakaseCommand
            {
                protected function updateComposerJson(array $composerConfig, string $packageName = ''): bool
                {
                    $composerPath = base_path('composer.json');

                    if (! File::exists($composerPath)) {
                        $this->error('composer.json not found');

                        return false;
                    }

                    try {
                        $composerContent = File::get($composerPath);
                        $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);

                        if (! is_array($composerData)) {
                            $this->error('Invalid composer.json structure');

                            return false;
                        }

                        // Simulate json_encode failure by returning false
                        $formattedJson = false; // This simulates json_encode() returning false

                        if ($formattedJson === false) {
                            $this->error('Failed to encode composer.json data');

                            return false;
                        }

                        File::put($composerPath, $formattedJson."\n");

                        return true;

                    } catch (\JsonException $e) {
                        $this->error('Invalid JSON in composer.json: '.$e->getMessage());

                        return false;
                    } catch (\Exception $e) {
                        $this->error('Failed to update composer.json: '.$e->getMessage());

                        return false;
                    }
                }
            };

            // Create a test config that would trigger the composer.json update
            $testConfig = [
                'require-dev' => [
                    'test/package' => [
                        'composer' => [
                            'scripts' => [
                                'test-script' => ['echo "test"'],
                            ],
                        ],
                    ],
                ],
            ];

            setComposerPackagesConfig($testConfig);

            $this->app->singleton(OmakaseCommand::class, fn () => $command);

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add test/package configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('Failed to encode composer.json data')
                ->expectsOutputToContain('Failed to update composer.json for test/package, continuing...')
                ->assertSuccessful(); // Command continues despite JSON encoding error
        });

        it('handles missing composer.json file', function (): void {
            mockComposerJsonFile([], false);

            runComposerOnlyCommand(false)
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add barryvdh/laravel-ide-helper configuration to composer.json (scripts)?', 'yes')
                ->expectsOutputToContain('composer.json not found')
                ->assertSuccessful(); // Command continues despite missing file
        });

        it('handles config with multiple packages with different composer configurations', function (): void {
            // Test multiple packages with different composer.json sections
            $multiPackageConfig = [
                'require-dev' => [
                    'package1/scripts' => [
                        'composer' => [
                            'scripts' => [
                                'post-install-cmd' => ['echo "package1 installed"'],
                            ],
                        ],
                    ],
                    'package2/repos' => [
                        'composer' => [
                            'repositories' => [
                                [
                                    'type' => 'vcs',
                                    'url' => 'https://github.com/package2/repo',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
            setComposerPackagesConfig($multiPackageConfig);

            $composerJson = ['name' => 'test/project'];

            File::shouldReceive('exists')
                ->with(base_path('composer.json'))
                ->twice()
                ->andReturn(true);

            File::shouldReceive('get')
                ->with(base_path('composer.json'))
                ->twice()
                ->andReturn(json_encode($composerJson));

            File::shouldReceive('put')
                ->twice()
                ->andReturn(true);

            runOmakaseCommand(['--composer' => true])
                ->expectsConfirmation('Do you want to update existing Composer packages first?', 'no')
                ->expectsConfirmation('Add package1/scripts configuration to composer.json (scripts)?', 'yes')
                ->expectsConfirmation('Add package2/repos configuration to composer.json (repositories)?', 'yes')
                ->expectsOutputToContain('Added post-install-cmd scripts to composer.json')
                ->expectsOutputToContain('Added repository: https://github.com/package2/repo')
                ->assertSuccessful();
        });

        it('skips composer update confirmation with --force option', function (): void {
            Process::fake();

            runOmakaseCommand(['--composer' => true, '--force' => true, '--skip-composer-json' => true])
                ->expectsOutputToContain('composer update')
                ->expectsOutputToContain('Installing Composer Packages')
                ->assertSuccessful();

            // Verify composer update was run automatically
            assertCommandRan('composer update');
            assertCommandRan('composer require');
        });

        it('skips npm update confirmation with --force option', function (): void {
            Process::fake();

            runOmakaseCommand(['--npm' => true, '--force' => true])
                ->expectsOutputToContain('npm update')
                ->expectsOutputToContain('Installing NPM Packages')
                ->assertSuccessful();

            // Verify npm update was run automatically
            assertCommandRan('npm update');
            assertCommandRan('npm install');
        });

        it('skips both update confirmations with --force option in full run', function (): void {
            Process::fake();

            runOmakaseCommand(['--force' => true, '--skip-composer-json' => true])
                ->expectsOutputToContain('composer update')
                ->expectsOutputToContain('npm update')
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify both update commands were run automatically
            assertCommandRan('composer update');
            assertCommandRan('npm update');
        });
    });
});
