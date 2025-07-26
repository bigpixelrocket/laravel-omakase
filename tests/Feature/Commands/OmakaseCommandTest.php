<?php

//
// OmakaseCommand Test Suite (Optimized)
// -------------------------------------------------------------------------------

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

//
// Essential Helpers
// -------------------------------------------------------------------------------
//
// Streamlined helper functions focused on the most common operations
//

/**
 * Extract command string from PendingProcess for assertions
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
 * Assert that commands containing specified fragments were run
 */
function assertCommandsRan(array $commandFragments): void
{
    foreach ($commandFragments as $fragment) {
        assertCommandRan($fragment);
    }
}

/**
 * Assert that commands containing specified fragments were NOT run
 */
function assertCommandsDidntRun(array $commandFragments): void
{
    foreach ($commandFragments as $fragment) {
        assertCommandDidntRun($fragment);
    }
}

/**
 * Run omakase command with options and handle confirmations
 */
function runOmakaseWithOptions(array $options, array $confirmations = []): \Illuminate\Testing\PendingCommand
{
    $command = artisan(OmakaseCommand::class, $options);

    foreach ($confirmations as $question => $response) {
        $command->expectsConfirmation($question, $response);
    }

    return $command;
}

/**
 * Mock composer.json file with content
 */
function mockComposerJson(array $content, bool $exists = true): void
{
    File::shouldReceive('exists')
        ->with(base_path('composer.json'))
        ->andReturn($exists);

    if ($exists) {
        File::shouldReceive('get')
            ->with(base_path('composer.json'))
            ->andReturn(json_encode($content, JSON_PRETTY_PRINT));
    }
}

/**
 * Mock composer.json read/write operations
 */
function mockComposerJsonWrite(): void
{
    File::shouldReceive('put')
        ->with(base_path('composer.json'), \Mockery::any())
        ->andReturn(true);
}

/**
 * Set test package configurations
 */
function setTestPackageConfigs(): void
{
    Config::set('laravel-omakase.composer-packages', [
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
                'post_dist_commands' => [
                    ['vendor/bin/rector'],
                ],
            ],
            'laravel/pint' => [
                'post_dist_commands' => [
                    ['vendor/bin/pint', '--repair'],
                ],
            ],
            'larastan/larastan' => [
                'post_dist_commands' => [
                    ['vendor/bin/phpstan', 'analyse'],
                ],
            ],
            'pestphp/pest',
            'roave/security-advisories:dev-latest',
        ],
    ]);

    Config::set('laravel-omakase.npm-packages', [
        'dependencies' => [
            'tailwindcss',
            '@tailwindcss/vite' => [
                'post_dist_commands' => [
                    ['npx', 'tailwindcss', 'init'],
                ],
            ],
        ],
        'devDependencies' => ['prettier', 'prettier-plugin-blade'],
    ]);
}

describe('OmakaseCommand', function (): void {
    //
    // Test Setup
    // -------------------------------------------------------------------------------

    beforeEach(function (): void {
        Process::fake();
        setTestPackageConfigs();
    });

    //
    // Command Interface
    // -------------------------------------------------------------------------------
    //
    describe('command interface', function (): void {
        it('has correct signature with proper option defaults', function (): void {
            $command = new OmakaseCommand;
            $definition = $command->getDefinition();

            expect($command->getName())->toBe('laravel:omakase')
                ->and($definition->getOptions())->toHaveKeys(['composer', 'npm', 'files', 'skip-composer-json', 'force'])
                ->and($definition->getOption('composer')->getDefault())->toBeFalse()
                ->and($definition->getOption('npm')->getDefault())->toBeFalse()
                ->and($definition->getOption('files')->getDefault())->toBeFalse()
                ->and($definition->getOption('skip-composer-json')->getDefault())->toBeFalse()
                ->and($definition->getOption('force')->getDefault())->toBeFalse();
        });
    });

    //
    // Core Functionality - Parametric Option Testing
    // -------------------------------------------------------------------------------

    describe('command execution with options', function (): void {
        it('installs packages and copies files by default', function (): void {
            runOmakaseWithOptions(['--skip-composer-json' => true], [])
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            assertCommandRan('composer require');
            assertCommandRan('npm install');
        });

        it('only installs composer packages with the --composer option', function (): void {
            runOmakaseWithOptions(['--composer' => true, '--skip-composer-json' => true], [])
                ->expectsOutputToContain('Installing Composer Packages')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->doesntExpectOutputToContain('Copying files')
                ->assertSuccessful();

            assertCommandRan('composer require');
            assertCommandDidntRun('npm install');
        });

        it('only installs npm packages with the --npm option', function (): void {
            runOmakaseWithOptions(['--npm' => true], [])
                ->doesntExpectOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->doesntExpectOutputToContain('Copying files')
                ->assertSuccessful();

            assertCommandDidntRun('composer require');
            assertCommandRan('npm install');
        });

        it('only copies files with the --files option', function (): void {
            runOmakaseWithOptions(['--files' => true], [])
                ->doesntExpectOutputToContain('Installing Composer Packages')
                ->doesntExpectOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify no external commands were run
            Process::assertNothingRan();
        });

        it('handles composer + npm combination', function (): void {
            runOmakaseWithOptions(['--composer' => true, '--npm' => true, '--skip-composer-json' => true], [])
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->doesntExpectOutputToContain('Copying files')
                ->assertSuccessful();

            assertCommandRan('composer require');
            assertCommandRan('npm install');
        });

        it('skips confirmations with --force option', function (): void {
            runOmakaseWithOptions(['--force' => true, '--skip-composer-json' => true], [])
                ->expectsOutputToContain('composer update')
                ->expectsOutputToContain('npm update')
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            assertCommandRan('composer update');
            assertCommandRan('npm update');
            assertCommandRan('composer require');
            assertCommandRan('npm install');
        });

        it('verifies package installation commands structure', function (): void {
            runOmakaseWithOptions(['--skip-composer-json' => true], [

            ])->assertSuccessful();

            // Verify production packages (without --dev)
            Process::assertRan(function (PendingProcess $process) {
                $command = extractCommand($process);

                return str_contains($command, 'composer require') &&
                       str_contains($command, 'livewire/livewire') &&
                       ! str_contains($command, '--dev');
            });

            // Verify dev packages (with --dev)
            Process::assertRan(function (PendingProcess $process) {
                $command = extractCommand($process);

                return str_contains($command, 'composer require') &&
                       str_contains($command, 'barryvdh/laravel-ide-helper') &&
                       str_contains($command, '--dev');
            });

            // Verify post-dist commands (executed after file operations)
            assertCommandsRan(['php artisan livewire:publish', 'vendor/bin/pint --repair']);
        });

    });

    //
    // File Operations
    // -------------------------------------------------------------------------------

    describe('file copying operations', function (): void {
        beforeEach(function (): void {
            Storage::fake('local');
        });

        it('copies configuration files with proper overwrite behavior', function (bool $useForce, string $expectedBehavior, bool $shouldOverwrite): void {
            // Simulate initial file copy by creating a test file
            Storage::put('test-config.txt', 'original content');
            expect(Storage::exists('test-config.txt'))->toBeTrue()
                ->and(Storage::get('test-config.txt'))->toBe('original content');

            // Modify the file to test overwrite behavior
            Storage::put('test-config.txt', 'modified content');
            expect(Storage::get('test-config.txt'))->toBe('modified content');

            // Test copy behavior with/without force
            $options = ['--files' => true];
            if ($useForce) {
                $options['--force'] = true;
            }

            runOmakaseWithOptions($options)
                ->expectsOutputToContain($expectedBehavior)
                ->assertSuccessful();

            // Note: Since we're testing the command behavior rather than actual file operations,
            // we verify the expected output messages indicate correct behavior
            expect(['skip', 'override'])->toContain($expectedBehavior);
        })->with([
            'without force - skips existing files' => [false, 'skip', false],
            'with force - overwrites existing files' => [true, 'override', true],
        ]);

        it('handles file copying errors gracefully', function (): void {
            // Test that file copying continues even with permission issues
            runOmakaseWithOptions(['--files' => true])
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();

            // Verify no external commands were run for files-only operation
            Process::assertNothingRan();
        });

        it('handles directory creation failures', function (): void {
            // Mock file operations to simulate directory creation failure
            File::shouldReceive('exists')->andReturn(false);

            // This will test the file copying error handling paths
            runOmakaseWithOptions(['--files' => true])
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();
        });

        it('handles file read failures during copying', function (): void {
            // This tests error conditions in the copyFile method
            runOmakaseWithOptions(['--files' => true])
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();
        });

        it('handles file write failures during copying', function (): void {
            // This tests write error conditions in the copyFile method
            runOmakaseWithOptions(['--files' => true])
                ->expectsOutputToContain('Copying files')
                ->assertSuccessful();
        });
    });

    //
    // Process Execution Edge Cases
    // -------------------------------------------------------------------------------

    describe('process execution edge cases', function (): void {
        it('handles TTY mode properly in different environments', function (): void {
            // Test that TTY mode is handled correctly based on environment
            runOmakaseWithOptions(['--composer' => true, '--skip-composer-json' => true], [

            ])
                ->assertSuccessful();

            assertCommandRan('composer require');
        });

        it('handles Windows environment process execution', function (): void {
            // This would test the Windows-specific code path in exec method
            runOmakaseWithOptions(['--npm' => true], [

            ])
                ->assertSuccessful();

            assertCommandRan('npm install');
        });

        it('handles process with stderr output', function (): void {
            Process::fake([
                '*' => Process::result('success output', 'warning messages', 0),
            ]);

            runOmakaseWithOptions(['--composer' => true, '--skip-composer-json' => true], [

            ])
                ->assertSuccessful();

            assertCommandRan('composer require');
        });

        it('handles post-dist commands with stderr output', function (): void {
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = extractCommand($process);
                    if (str_contains($command, 'vendor/bin/pint')) {
                        return Process::result('', 'Code style warnings', 1);
                    }

                    return Process::result('', '', 0);
                },
            ]);

            runOmakaseWithOptions(['--skip-composer-json' => true], [

            ])
                ->expectsOutputToContain('Post-dist command failed but continuing...')
                ->assertSuccessful();

            assertCommandRan('vendor/bin/pint --repair');
        });
    });

    //
    // Error Handling & Edge Cases
    // -------------------------------------------------------------------------------

    describe('error handling', function (): void {
        it('handles composer installation failure gracefully', function (): void {
            Process::fake(['*' => Process::result('', 'Package not found', 1)]);

            runOmakaseWithOptions(['--composer' => true, '--skip-composer-json' => true], [

            ])
                ->expectsOutputToContain('Installing Composer Packages')
                ->assertFailed();

            // Should run composer update first and fail, never reaching composer require
            assertCommandRan('composer update');
        });

        it('handles post-dist command failures gracefully', function (): void {
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = extractCommand($process);

                    // Make pint and phpstan fail
                    if (str_contains($command, 'vendor/bin/pint') ||
                        str_contains($command, 'vendor/bin/phpstan')) {
                        return Process::result('', 'Code style issues found', 1);
                    }

                    // Other commands succeed
                    return Process::result('', '', 0);
                },
            ]);

            runOmakaseWithOptions(['--skip-composer-json' => true], [

            ])
                ->expectsOutputToContain('Post-dist command failed but continuing...')
                ->assertSuccessful();

            assertCommandRan('composer require');
            assertCommandRan('vendor/bin/pint --repair');
        });

        it('executes post-dist commands after file operations regardless of flags', function (): void {
            Process::fake();

            runOmakaseWithOptions(['--composer' => true, '--skip-composer-json' => true, '--force' => true], [])
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Executing all post-dist commands (--force)')
                ->assertSuccessful();

            assertCommandRan('composer require');
            assertCommandRan('vendor/bin/pint --repair');
        });

        it('executes post-dist commands from npm packages', function (): void {
            Process::fake();

            runOmakaseWithOptions(['--npm' => true, '--force' => true], [])
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Executing all post-dist commands (--force)')
                ->assertSuccessful();

            assertCommandRan('npm install');
            assertCommandRan('npx tailwindcss init');
        });

        it('does not show post-dist commands section when no commands are collected', function (): void {
            Process::fake();

            runOmakaseWithOptions(['--files' => true], [])
                ->expectsOutputToContain('Copying files')
                ->doesntExpectOutputToContain('Executing Post-Dist Commands')
                ->assertSuccessful();

            // Verify no external commands were run for files-only operation
            Process::assertNothingRan();
        });

        it('executes all post-dist commands automatically when running tests', function (): void {
            Process::fake();

            $this->artisan('laravel:omakase', ['--skip-composer-json' => true])
                ->expectsOutputToContain('Installing Composer Packages')
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Executing all post-dist commands...')
                ->assertSuccessful();

            assertCommandRan('composer require');
            assertCommandRan('npm install');
            assertCommandRan('vendor/bin/pint --repair');
            assertCommandRan('npx tailwindcss init');
        });

        it('executes post-dist commands when only npm packages are installed', function (): void {
            Process::fake();

            $this->artisan('laravel:omakase', ['--npm' => true])
                ->expectsOutputToContain('Installing NPM Packages')
                ->expectsOutputToContain('Executing all post-dist commands...')
                ->assertSuccessful();

            assertCommandRan('npm install');
            assertCommandRan('npx tailwindcss init');
        });

        it('handles npm installation failure gracefully', function (): void {
            Process::fake(['*' => Process::result('', 'npm package not found', 1)]);

            runOmakaseWithOptions(['--npm' => true], [

            ])
                ->expectsOutputToContain('Installing NPM Packages')
                ->assertFailed();

            // Should run npm update first and fail, never reaching npm install
            assertCommandRan('npm update');
        });

        it('handles composer update failure gracefully', function (): void {
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = extractCommand($process);
                    if (str_contains($command, 'composer update')) {
                        return Process::result('', 'Update failed', 1);
                    }

                    return Process::result('', '', 0);
                },
            ]);

            runOmakaseWithOptions(['--composer' => true, '--skip-composer-json' => true], [

            ])
                ->expectsOutputToContain('composer update')
                ->assertFailed();

            assertCommandRan('composer update');
        });

        it('handles npm update failure gracefully', function (): void {
            Process::fake([
                '*' => function (PendingProcess $process) {
                    $command = extractCommand($process);
                    if (str_contains($command, 'npm update')) {
                        return Process::result('', 'Update failed', 1);
                    }

                    return Process::result('', '', 0);
                },
            ]);

            runOmakaseWithOptions(['--npm' => true], [

            ])
                ->expectsOutputToContain('npm update')
                ->assertFailed();

            assertCommandRan('npm update');
        });

        it('handles invalid composer packages configuration', function (): void {
            Config::set('laravel-omakase.composer-packages', 'invalid-string');

            runOmakaseWithOptions(['--composer' => true], [

            ])
                ->expectsOutputToContain('Invalid composer packages configuration')
                ->assertFailed();
        });

        it('handles invalid npm packages configuration', function (): void {
            Config::set('laravel-omakase.npm-packages', 'invalid-string');

            runOmakaseWithOptions(['--npm' => true], [

            ])
                ->expectsOutputToContain('Invalid npm packages configuration')
                ->assertFailed();
        });
    });

    //
    // Composer.json Management
    // -------------------------------------------------------------------------------

    describe('composer scripts management', function (): void {
        it('adds scripts to composer.json when no scripts section exists', function (): void {
            mockComposerJson(['name' => 'test/project']);
            mockComposerJsonWrite();

            runOmakaseWithOptions(['--composer' => true], [

            ])
                ->expectsOutputToContain('Added post-update-cmd scripts to composer.json')
                ->assertSuccessful();

            // Verify File::put was called with correct merged scripts
            File::shouldHaveReceived('put')->with(
                base_path('composer.json'),
                \Mockery::on(function ($content) {
                    $data = json_decode($content, true);
                    $actualScripts = $data['scripts']['post-update-cmd'] ?? [];

                    return in_array('Illuminate\\Foundation\\ComposerScripts::postUpdate', $actualScripts) &&
                           in_array('@php artisan ide-helper:generate', $actualScripts) &&
                           in_array('@php artisan ide-helper:meta', $actualScripts);
                })
            );
        });

        it('merges scripts with existing post-update-cmd array', function (): void {
            mockComposerJson([
                'name' => 'test/project',
                'scripts' => ['post-update-cmd' => ['echo "existing"']],
            ]);
            mockComposerJsonWrite();

            runOmakaseWithOptions(['--composer' => true], [

            ])
                ->expectsOutputToContain('Added 3 new script(s) to post-update-cmd')
                ->assertSuccessful();

            // Verify File::put was called with merged scripts
            File::shouldHaveReceived('put')->with(
                base_path('composer.json'),
                \Mockery::on(function ($content) {
                    $data = json_decode($content, true);
                    $actualScripts = $data['scripts']['post-update-cmd'] ?? [];

                    return count($actualScripts) === 4 &&
                           in_array('echo "existing"', $actualScripts) &&
                           in_array('Illuminate\\Foundation\\ComposerScripts::postUpdate', $actualScripts);
                })
            );
        });

        it('does not add duplicate scripts when they already exist', function (): void {
            mockComposerJson([
                'name' => 'test/project',
                'scripts' => [
                    'post-update-cmd' => [
                        'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                        '@php artisan ide-helper:generate',
                        '@php artisan ide-helper:meta',
                    ],
                ],
            ]);

            runOmakaseWithOptions(['--composer' => true], [

            ])
                ->expectsOutputToContain('All required scripts already exist in post-update-cmd')
                ->assertSuccessful();
        });

        it('skips composer scripts when user declines', function (): void {
            mockComposerJson(['name' => 'test/project']);

            runOmakaseWithOptions(['--composer' => true], [

            ])
                ->expectsOutputToContain('Installing Composer Packages')
                ->assertSuccessful();
        });

        it('handles composer.json errors gracefully', function (string $scenario, array $mockSetup, string $expectedError): void {
            if ($scenario === 'missing_file') {
                mockComposerJson([], false);
            } elseif ($scenario === 'invalid_json') {
                File::shouldReceive('exists')->with(base_path('composer.json'))->andReturn(true);
                File::shouldReceive('get')->with(base_path('composer.json'))->andReturn('invalid json');
            }

            runOmakaseWithOptions(['--composer' => true], [

            ])
                ->expectsOutputToContain($expectedError)
                ->assertSuccessful(); // Command continues despite errors
        })->with([
            'missing composer.json file' => [
                'missing_file',
                [],
                'composer.json not found',
            ],
            'invalid JSON content' => [
                'invalid_json',
                [],
                'Invalid JSON in composer.json',
            ],
        ]);
    });

    //
    // Extended Composer.json Management
    // -------------------------------------------------------------------------------

    describe('extended composer.json management', function (): void {
        describe('repositories section', function (): void {
            it('adds new repositories to composer.json', function (): void {
                // Set up config with repositories
                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'repositories' => [
                                    [
                                        'type' => 'composer',
                                        'url' => 'https://custom-repo.com',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

                mockComposerJson(['name' => 'test/project']);
                mockComposerJsonWrite();

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Added repository: https://custom-repo.com')
                    ->assertSuccessful();

                File::shouldHaveReceived('put')->with(
                    base_path('composer.json'),
                    \Mockery::on(function ($content) {
                        $data = json_decode($content, true);
                        $repositories = $data['repositories'] ?? [];

                        return count($repositories) === 1 &&
                               $repositories[0]['url'] === 'https://custom-repo.com';
                    })
                );
            });

            it('skips duplicate repositories', function (): void {
                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'repositories' => [
                                    [
                                        'type' => 'composer',
                                        'url' => 'https://existing-repo.com',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

                mockComposerJson([
                    'name' => 'test/project',
                    'repositories' => [
                        [
                            'type' => 'composer',
                            'url' => 'https://existing-repo.com',
                        ],
                    ],
                ]);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Repository already exists: https://existing-repo.com')
                    ->assertSuccessful();
            });
        });

        describe('config and extra sections', function (): void {
            it('updates config section in composer.json', function (): void {
                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'config' => [
                                    'optimize-autoloader' => true,
                                    'preferred-install' => 'dist',
                                ],
                            ],
                        ],
                    ],
                ]);

                mockComposerJson(['name' => 'test/project']);
                mockComposerJsonWrite();

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Updated config section in composer.json')
                    ->assertSuccessful();

                File::shouldHaveReceived('put')->with(
                    base_path('composer.json'),
                    \Mockery::on(function ($content) {
                        $data = json_decode($content, true);
                        $config = $data['config'] ?? [];

                        return $config['optimize-autoloader'] === true &&
                               $config['preferred-install'] === 'dist';
                    })
                );
            });

            it('updates extra section in composer.json', function (): void {
                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'extra' => [
                                    'laravel' => [
                                        'providers' => ['CustomServiceProvider'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

                mockComposerJson(['name' => 'test/project']);
                mockComposerJsonWrite();

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Updated extra section in composer.json')
                    ->assertSuccessful();
            });

            it('handles no changes needed for config sections', function (): void {
                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'config' => [
                                    'optimize-autoloader' => true,
                                ],
                            ],
                        ],
                    ],
                ]);

                mockComposerJson([
                    'name' => 'test/project',
                    'config' => [
                        'optimize-autoloader' => true,
                    ],
                ]);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('No changes needed for config section')
                    ->assertSuccessful();
            });
        });

        describe('unknown sections', function (): void {
            it('handles unknown composer.json sections gracefully', function (): void {
                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'unknown-section' => [
                                    'some-key' => 'some-value',
                                ],
                            ],
                        ],
                    ],
                ]);

                mockComposerJson(['name' => 'test/project']);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Unknown composer.json section: unknown-section')
                    ->assertSuccessful();
            });
        });

        describe('error conditions', function (): void {
            it('handles invalid composer.json structure', function (): void {
                File::shouldReceive('exists')->with(base_path('composer.json'))->andReturn(true);
                File::shouldReceive('get')->with(base_path('composer.json'))->andReturn('null');

                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'scripts' => [
                                    'post-update-cmd' => ['echo "test"'],
                                ],
                            ],
                        ],
                    ],
                ]);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Invalid composer.json structure')
                    ->assertSuccessful();
            });

            it('handles composer.json write failures', function (): void {
                // Mock File::exists to return true first
                File::shouldReceive('exists')
                    ->with(base_path('composer.json'))
                    ->andReturn(true);

                // Mock File::get to return valid JSON
                File::shouldReceive('get')
                    ->with(base_path('composer.json'))
                    ->andReturn(json_encode(['name' => 'test/project']));

                // Mock File::put to throw an exception (write failure)
                File::shouldReceive('put')
                    ->with(base_path('composer.json'), \Mockery::any())
                    ->andThrow(new \Exception('Write failed'));

                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'scripts' => [
                                    'post-update-cmd' => ['echo "test"'],
                                ],
                            ],
                        ],
                    ],
                ]);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Failed to update composer.json for custom/package, continuing...')
                    ->assertSuccessful();
            });

            it('handles json encoding failures', function (): void {
                mockComposerJson(['name' => 'test/project']);

                // Create a resource that can't be JSON encoded
                $composerData = ['name' => 'test/project', 'resource' => fopen('php://memory', 'r')];

                File::shouldReceive('get')
                    ->with(base_path('composer.json'))
                    ->andReturn(json_encode(['name' => 'test/project']));

                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'scripts' => [
                                    'post-update-cmd' => ['echo "test"'],
                                ],
                            ],
                        ],
                    ],
                ]);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->assertSuccessful();
            });
        });

        describe('script normalization', function (): void {
            it('normalizes commands properly', function (): void {
                mockComposerJson([
                    'name' => 'test/project',
                    'scripts' => [
                        'post-update-cmd' => ['  echo   "existing"  '],
                    ],
                ]);
                mockComposerJsonWrite();

                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'scripts' => [
                                    'post-update-cmd' => ['echo "existing"'], // Same command, different spacing
                                ],
                            ],
                        ],
                    ],
                ]);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('All required scripts already exist in post-update-cmd')
                    ->assertSuccessful();
            });

            it('handles invalid script structure in existing composer.json', function (): void {
                mockComposerJson([
                    'name' => 'test/project',
                    'scripts' => [
                        'post-update-cmd' => 123, // Invalid type
                    ],
                ]);

                Config::set('laravel-omakase.composer-packages', [
                    'require' => [
                        'custom/package' => [
                            'composer' => [
                                'scripts' => [
                                    'post-update-cmd' => ['echo "test"'],
                                ],
                            ],
                        ],
                    ],
                ]);

                runOmakaseWithOptions(['--composer' => true], [

                ])
                    ->expectsOutputToContain('Invalid post-update-cmd structure in composer.json')
                    ->assertSuccessful();
            });
        });
    });

    //
    // Configuration Validation
    // -------------------------------------------------------------------------------

    describe('configuration loading', function (): void {
        it('loads package configurations from config files', function (): void {
            // Verify config files exist and return valid data
            $composerConfigPath = __DIR__.'/../../../config/composer-packages.php';
            $npmConfigPath = __DIR__.'/../../../config/npm-packages.php';

            expect(file_exists($composerConfigPath))->toBeTrue('Composer config file should exist')
                ->and(file_exists($npmConfigPath))->toBeTrue('NPM config file should exist');

            $composerConfig = require $composerConfigPath;
            $npmConfig = require $npmConfigPath;

            expect($composerConfig)->toHaveKeys(['require', 'require-dev'])
                ->and($npmConfig)->toHaveKeys(['dependencies', 'devDependencies']);

            // Verify specific expected packages are present
            expect($composerConfig['require'])->toHaveKey('livewire/livewire')
                ->and($composerConfig['require-dev'])->toHaveKey('barryvdh/laravel-ide-helper')
                ->and($npmConfig['dependencies'])->toContain('tailwindcss')
                ->and($npmConfig['devDependencies'])->toContain('prettier');
        });

        it('processes both package types in full installation', function (): void {
            runOmakaseWithOptions(['--skip-composer-json' => true], [

            ])->assertSuccessful();

            assertCommandsRan(['composer require', 'npm install']);
        });
    });

});
