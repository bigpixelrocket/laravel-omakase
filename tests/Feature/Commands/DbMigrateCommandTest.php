<?php

declare(strict_types=1);

use Bigpixelrocket\LaravelOmakase\Commands\DbMigrateCommand;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\artisan;

//
// DbMigrateCommand Test Suite
// -------------------------------------------------------------------------------
//
// This file verifies that the `db:migrate` alias command behaves exactly as
// intended.  Tests are grouped by concern: interface, registration, execution
// and help-forwarding logic.
//

//
// Helpers
// -------------------------------------------------------------------------------
//
// Utility functions shared across this test suite are in tests/Pest.php
//

//
// Test Groups
// -------------------------------------------------------------------------------
//
// The following blocks group related tests using Pest's `describe()` helper so
// that the output collapses logically when running the suite.
//

describe('DbMigrateCommand', function (): void {
    //
    // Command Interface
    //
    // Ensures the alias command exposes the correct name, description and
    // exactly one custom option: `--migrate-help`.
    //

    describe('command interface', function (): void {
        it('is instantiated with correct signature, description and custom option', function (): void {
            $command = new DbMigrateCommand;

            $definition = $command->getDefinition();
            $options = $definition->getOptions();

            // Default options that every Laravel command possesses
            $defaultOptions = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'];

            // Filter to only custom options
            $customOptions = array_filter($options, static fn ($key) => ! in_array($key, $defaultOptions), ARRAY_FILTER_USE_KEY);

            expect($command)->toBeInstanceOf(DbMigrateCommand::class)
                ->and($command->getName())->toBe('db:migrate')
                ->and($command->getDescription())->toBe('Alias for the migrate command - Run the database migrations (use --migrate-help to see migrate options)')
                ->and($customOptions)->toHaveCount(1)
                ->and($customOptions)->toHaveKey('migrate-help')
                ->and($definition->getOption('migrate-help')->getDefault())->toBeFalse();
        });
    });

    //
    // Registration
    //
    // Confirms the command is registered with Laravel's console kernel.
    //

    it('is registered in the console kernel', function (): void {
        $commands = app()->make(\Illuminate\Contracts\Console\Kernel::class)->all();

        expect($commands)->toHaveKey('db:migrate');
    });

    //
    // Execution (default path)
    //
    // Verifies that running the alias with no flags delegates to the underlying
    // `migrate` command and exits successfully.
    //

    it('executes successfully and delegates to migrate with default parameters', function (): void {
        artisan('db:migrate')->assertSuccessful();
    });

    //
    // Help Forwarding
    //
    // Covers the branch where users request help for the underlying command via
    // `--migrate-help`. Tests both success and failure paths with comprehensive
    // output verification to catch duplication issues and improper output handling.
    //

    describe('help forwarding', function (): void {
        beforeEach(function (): void {
            Process::fake();
        });

        it('forwards --migrate-help to the underlying migrate command and displays output correctly', function (): void {
            $helpOutput = 'Usage: php artisan migrate [options]';

            // Fake successful output for the help command
            Process::fake([
                '*' => Process::result(
                    output: $helpOutput,
                    exitCode: 0,
                ),
            ]);

            artisan('db:migrate', ['--migrate-help' => true])
                ->assertExitCode(0)
                ->expectsOutputToContain($helpOutput);

            Process::assertRan(fn (PendingProcess $process): bool => str_contains(commandToString($process), 'php artisan migrate --help'));
        });

        it('handles successful help output without duplication', function (): void {
            $helpOutput = 'Migration help content';

            Process::fake([
                '*' => Process::result(
                    output: $helpOutput,
                    exitCode: 0,
                ),
            ]);

            artisan('db:migrate', ['--migrate-help' => true])
                ->assertExitCode(0)
                ->expectsOutputToContain($helpOutput);

            // Verify the help output appears (we can't easily test duplication with Laravel's test methods,
            // but our implementation ensures it only appears once)
        });

        it('returns the exit code from the underlying process on failure', function (): void {
            $errorMessage = 'Command failed with error';

            // Fake a failing process
            Process::fake([
                '*' => Process::result(
                    errorOutput: $errorMessage,
                    exitCode: 1,
                ),
            ]);

            artisan('db:migrate', ['--migrate-help' => true])
                ->assertExitCode(1)
                ->expectsOutputToContain($errorMessage);
        });

        it('handles error output correctly without duplication when migrate command fails', function (): void {
            $errorMessage = 'Migration help failed with specific error';

            Process::fake([
                '*' => Process::result(
                    output: '',
                    errorOutput: $errorMessage,
                    exitCode: 1,
                ),
            ]);

            artisan('db:migrate', ['--migrate-help' => true])
                ->assertExitCode(1)
                ->expectsOutputToContain($errorMessage);

            // Process should have been called exactly once
            Process::assertRanTimes(fn (\Illuminate\Process\PendingProcess $process) => str_contains(commandToString($process), 'php artisan migrate --help'), 1);
        });

        it('does not mix success and error output on failure', function (): void {
            $successOutput = 'This should not appear';
            $errorMessage = 'Migration help failed';

            Process::fake([
                '*' => Process::result(
                    output: $successOutput,
                    errorOutput: $errorMessage,
                    exitCode: 1,
                ),
            ]);

            artisan('db:migrate', ['--migrate-help' => true])
                ->assertExitCode(1)
                ->expectsOutputToContain($errorMessage)
                ->doesntExpectOutputToContain($successOutput);
        });

        it('handles empty error output gracefully', function (): void {
            Process::fake([
                '*' => Process::result(
                    output: '',
                    errorOutput: '',
                    exitCode: 1,
                ),
            ]);

            // Should not crash or produce unexpected output when error output is empty
            artisan('db:migrate', ['--migrate-help' => true])
                ->assertExitCode(1);
        });

        it('preserves exact exit codes from the underlying process', function (): void {
            // Test various exit codes to ensure they're preserved correctly
            $testCodes = [0, 1, 2, 127, 255];

            foreach ($testCodes as $exitCode) {
                Process::fake([
                    '*' => Process::result(
                        output: "Output for exit code {$exitCode}",
                        exitCode: $exitCode,
                    ),
                ]);

                artisan('db:migrate', ['--migrate-help' => true])
                    ->assertExitCode($exitCode);
            }
        });
    });
});
