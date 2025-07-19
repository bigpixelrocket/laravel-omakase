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
// Utility functions shared across this test suite.
//

/**
 * Convert the command of a PendingProcess (string|array) to a readable string.
 */
function commandToString(PendingProcess $process): string
{
    return is_array($process->command)
        ? implode(' ', $process->command)
        : $process->command;
}

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
    // `--migrate-help`.  Both success and failure paths are asserted.
    //

    describe('help forwarding', function (): void {
        beforeEach(function (): void {
            Process::fake();
        });

        it('forwards --migrate-help to the underlying migrate command', function (): void {
            // Fake successful output for the help command
            Process::fake([
                '*' => Process::result(
                    output: 'Migration help output',
                    exitCode: 0,
                ),
            ]);

            artisan('db:migrate', ['--migrate-help' => true])->assertExitCode(0);

            Process::assertRan(function (PendingProcess $process): bool {
                return str_contains(commandToString($process), 'php artisan migrate --help');
            });
        });

        it('returns the exit code from the underlying process on failure', function (): void {
            // Fake a failing process
            Process::fake([
                '*' => Process::result(
                    errorOutput: 'Something went wrong',
                    exitCode: 1,
                ),
            ]);

            artisan('db:migrate', ['--migrate-help' => true])->assertExitCode(1);
        });
    });
});
