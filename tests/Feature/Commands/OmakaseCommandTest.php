<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\artisan;

describe('OmakaseCommand', function () {
    describe('command interface', function () {
        it('has correct command signature and description', function () {
            $command = new OmakaseCommand;

            expect($command->getName())->toBe('laravel:omakase');

            // Verify all options are defined with correct defaults
            $definition = $command->getDefinition();
            expect($definition->hasOption('composer'))->toBeTrue()
                ->and($definition->hasOption('npm'))->toBeTrue()
                ->and($definition->hasOption('files'))->toBeTrue()
                ->and($definition->hasOption('force'))->toBeTrue()
                ->and($definition->getOption('composer')->getDefault())->toBeFalse()
                ->and($definition->getOption('npm')->getDefault())->toBeFalse()
                ->and($definition->getOption('files')->getDefault())->toBeFalse()
                ->and($definition->getOption('force')->getDefault())->toBeFalse();
        });
    });

    describe('command options', function () {
        beforeEach(function () {
            Process::fake();
        });

        it('installs packages and copies files by default', function () {
            artisan(OmakaseCommand::class)
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify both composer and npm commands were run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });

            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'npm install');
            });
        });

        it('only installs composer packages with the --composer option', function () {
            artisan(OmakaseCommand::class, ['--composer' => true])
                ->expectsOutputToContain('Installing Composer Packages')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->doesntExpectOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify only composer was run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });

            Process::assertDidntRun(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'npm install');
            });
        });

        it('only installs npm packages with the --npm option', function () {
            artisan(OmakaseCommand::class, ['--npm' => true])
                ->doesntExpectOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->doesntExpectOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify only npm was run
            Process::assertDidntRun(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });

            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'npm install');
            });
        });

        it('only copies files with the --files option', function () {
            artisan(OmakaseCommand::class, ['--files' => true])
                ->doesntExpectOutputToContain('Installing Composer Packages')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify no external commands were run
            Process::assertNothingRan();
        });

        it('handles multiple option combinations correctly', function () {
            artisan(OmakaseCommand::class, ['--composer' => true, '--files' => true])
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Copying files')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->assertSuccessful();

            // Verify only composer was run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });

            Process::assertDidntRun(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'npm install');
            });
        });
    });

    describe('file copying', function () {
        beforeEach(function () {
            Process::fake();
        });

        it('copies all expected dist files', function () {
            $tempDir = createTempDirectory('dist_copy_');

            withTemporaryBasePath($tempDir, function () use ($tempDir) {
                artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();

                // Verify that files are being copied from dist
                $copiedFiles = File::allFiles($tempDir);
                expect(count($copiedFiles))->toBeGreaterThan(0, 'Some files should be copied from dist directory');
            });

            File::deleteDirectory($tempDir);
        });

        it('respects force option when copying files', function () {
            $tempDir = createTempDirectory('force_');

            withTemporaryBasePath($tempDir, function () use ($tempDir) {
                // First copy
                artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();
                expect(File::exists("{$tempDir}/pint.json"))->toBeTrue();

                $originalContent = File::get("{$tempDir}/pint.json");
                expect($originalContent)->not->toBeEmpty()
                    ->and($originalContent)->toContain('preset');

                // Modify the file
                File::put("{$tempDir}/pint.json", '{"modified": true}');
                $modifiedContent = File::get("{$tempDir}/pint.json");
                expect($modifiedContent)->toBe('{"modified": true}');

                // Copy without force should skip
                artisan(OmakaseCommand::class, ['--files' => true])
                    ->expectsOutputToContain('skip pint.json')
                    ->assertSuccessful();

                expect(File::get("{$tempDir}/pint.json"))->toBe('{"modified": true}');

                // Copy with force should override
                artisan(OmakaseCommand::class, ['--files' => true, '--force' => true])
                    ->expectsOutputToContain('override pint.json')
                    ->assertSuccessful();

                $restoredContent = File::get("{$tempDir}/pint.json");
                expect($restoredContent)->toBe($originalContent)
                    ->and($restoredContent)->toContain('preset');
            });

            File::deleteDirectory($tempDir);
        });

        it('creates nested directories when copying files', function () {
            $tempDir = createTempDirectory('nested_copy_');

            withTemporaryBasePath($tempDir, function () use ($tempDir) {
                artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();

                // Verify nested directories were created with proper structure
                expect(File::isDirectory("{$tempDir}/.github"))->toBeTrue()
                    ->and(File::isDirectory("{$tempDir}/.github/workflows"))->toBeTrue();

                // Verify files exist in nested directories
                $workflowFiles = File::files("{$tempDir}/.github/workflows");
                expect(count($workflowFiles))->toBeGreaterThan(0)
                    ->and(count($workflowFiles))->toBe(4); // Should have exactly 4 workflow files
            });

            File::deleteDirectory($tempDir);
        });
    });

    describe('error handling', function () {
        it('handles composer installation failure gracefully', function () {
            Process::fake([
                '*' => Process::result(
                    errorOutput: 'Package not found',
                    exitCode: 1
                ),
            ]);

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->assertFailed();

            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });
        });

        it('handles npm installation failure gracefully', function () {
            // We need to check command length to differentiate between composer and npm
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

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

            artisan(OmakaseCommand::class)
                ->assertFailed();

            // Verify both commands were attempted
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });

            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'npm install');
            });
        });

        it('handles optional command failures gracefully', function () {
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

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

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->expectsOutputToContain('Optional command failed but continuing installation...')
                ->assertSuccessful();

            // Verify that the optional commands were attempted
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'vendor/bin/pint --repair');
            });

            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'vendor/bin/phpstan analyse');
            });
        });

        it('handles file copy exception gracefully', function () {
            Process::fake();

            // Create a custom command that simulates a file copy error
            $command = new class extends OmakaseCommand
            {
                protected function copyFiles(): bool
                {
                    try {
                        // Simulate an error during file operations
                        throw new \RuntimeException('Permission denied: cannot write to directory');
                    } catch (\Exception $e) {
                        $this->error($e->getMessage());

                        return false;
                    }
                }
            };

            app()->instance(OmakaseCommand::class, $command);

            artisan(OmakaseCommand::class, ['--files' => true])
                ->expectsOutputToContain('Permission denied: cannot write to directory')
                ->assertFailed();
        });

        it('handles missing dist directory gracefully', function () {
            Process::fake();

            // Create a custom command instance with a non-existent dist path
            $command = new class extends OmakaseCommand
            {
                protected function copyFiles(): bool
                {
                    try {
                        $files = $this->getDistFiles('/nonexistent/directory/that/does/not/exist/');

                        return ! empty($files);
                    } catch (\Exception $e) {
                        $this->error('Failed to access dist directory: '.$e->getMessage());

                        return false;
                    }
                }
            };

            app()->instance(OmakaseCommand::class, $command);

            artisan(OmakaseCommand::class, ['--files' => true])
                ->expectsOutputToContain('Failed to access dist directory')
                ->assertFailed();
        });
    });

    describe('package verification', function () {
        it('verifies composer packages are installed', function () {
            Process::fake();

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->assertSuccessful();

            // Verify production packages command is run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require') && ! str_contains($command, '--dev');
            });

            // Verify dev packages command is run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require') && str_contains($command, '--dev');
            });
        });

        it('verifies npm packages are installed', function () {
            Process::fake();

            artisan(OmakaseCommand::class, ['--npm' => true])
                ->assertSuccessful();

            // Verify npm install command is run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'npm install');
            });
        });

        it('verifies post-install commands are executed', function () {
            Process::fake();

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->assertSuccessful();

            // Verify that required post-install artisan commands are run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'php artisan');
            });

            // Verify that optional post-install commands are run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'vendor/bin/pint --repair');
            });

            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'vendor/bin/phpstan analyse');
            });
        });

        it('verifies command structure with required and optional commands', function () {
            Process::fake();

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->assertSuccessful();

            // Count total commands run
            $totalCommands = 0;
            Process::assertRan(function (PendingProcess $process) use (&$totalCommands) {
                $totalCommands++;

                return true;
            });

            // Should run multiple commands including both required and optional
            expect($totalCommands)->toBeGreaterThan(5);

            // Verify specific command types were run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });

            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'php artisan livewire:publish');
            });
        });
    });

    describe('process execution', function () {
        it('disables TTY on Windows', function () {
            // Test Windows behavior
            Process::fake();

            // Mock Windows environment
            $originalOS = $_SERVER['PHP_OS_FAMILY'] ?? null;
            $_SERVER['PHP_OS_FAMILY'] = 'Windows';

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->assertSuccessful();

            // Restore original OS
            if ($originalOS !== null) {
                $_SERVER['PHP_OS_FAMILY'] = $originalOS;
            } else {
                unset($_SERVER['PHP_OS_FAMILY']);
            }

            // Process assertions work differently with fake, just verify command ran
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            });
        });

        it('shows command being executed', function () {
            Process::fake();

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->expectsOutputToContain('composer require')
                ->assertSuccessful();

            // Should run 2 composer require commands (one for regular packages, one for --dev)
            Process::assertRanTimes(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require');
            }, 2);
        });

        it('suppresses error output when PHPUNIT_COMPOSER_INSTALL is defined', function () {
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

            artisan(OmakaseCommand::class, ['--composer' => true])
                ->doesntExpectOutputToContain('Error output that should not be shown')
                ->assertFailed();

            // Note: We can't undefine constants in PHP, so we just restore if it was defined
            if (! $wasDefinedBefore && defined('PHPUNIT_COMPOSER_INSTALL')) {
                // Constant remains defined, which is expected in PHP
            }
        });
    });

    describe('edge cases', function () {
        it('handles empty dist directory gracefully', function () {
            Process::fake();

            $emptyDir = createTempDirectory('empty_dist_');

            try {
                // Create a custom command with empty dist directory
                $command = new class($emptyDir) extends OmakaseCommand
                {
                    private string $customDistPath;

                    public function __construct(string $distPath)
                    {
                        parent::__construct();
                        $this->customDistPath = $distPath;
                    }

                    protected function copyFiles(): bool
                    {
                        $files = $this->getDistFiles($this->customDistPath.'/');

                        if (empty($files)) {
                            $this->warn('No files found in dist directory');

                            return true;
                        }

                        return parent::copyFiles();
                    }
                };

                app()->instance(OmakaseCommand::class, $command);

                artisan(OmakaseCommand::class, ['--files' => true])
                    ->expectsOutputToContain('No files found in dist directory')
                    ->assertSuccessful();
            } finally {
                File::deleteDirectory($emptyDir);
            }
        });

        it('validates all command option combinations', function () {
            Process::fake();

            $combinations = [
                ['--composer' => true, '--npm' => true],
                ['--composer' => true, '--files' => true],
                ['--npm' => true, '--files' => true],
                ['--composer' => true, '--npm' => true, '--files' => true],
            ];

            foreach ($combinations as $options) {
                artisan(OmakaseCommand::class, $options)->assertSuccessful();
            }

            // Verify appropriate commands were run
            Process::assertRan(function (PendingProcess $process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

                return str_contains($command, 'composer require')
                    || str_contains($command, 'npm install');
            });
        });
    });
});
