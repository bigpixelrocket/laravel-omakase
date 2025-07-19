<?php

declare(strict_types=1);

use Bigpixelrocket\LaravelOmakase\Commands\DbMigrateCommand;

use function Pest\Laravel\artisan;

//
// DbMigrateCommand Test Suite
// -------------------------------------------------------------------------------
//
// This file verifies that the `db:migrate` alias command properly forwards
// options to the underlying migrate command.
//

describe('DbMigrateCommand', function (): void {
    //
    // Command Interface
    //
    // Ensures the alias command exposes the correct name, description and
    // all migrate command options.
    //

    describe('command interface', function (): void {
        it('has correct signature and description', function (): void {
            $command = new DbMigrateCommand;

            expect($command->getName())->toBe('db:migrate')
                ->and($command->getDescription())->toBe('Alias for the migrate command - Run the database migrations');
        });

        it('supports all migrate command options', function (): void {
            $command = new DbMigrateCommand;
            $definition = $command->getDefinition();
            $options = $definition->getOptions();

            // Default options that every Laravel command possesses
            $defaultOptions = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'];

            // Filter to only custom options
            $customOptions = array_filter($options, static fn ($key) => ! in_array($key, $defaultOptions), ARRAY_FILTER_USE_KEY);

            // Verify all migrate options are present
            $expectedOptions = [
                'database', 'force', 'path', 'realpath', 'schema-path',
                'pretend', 'seed', 'seeder', 'step', 'graceful', 'isolated',
            ];

            foreach ($expectedOptions as $option) {
                expect($customOptions)->toHaveKey($option);
            }

            expect($customOptions)->toHaveCount(count($expectedOptions));
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
    // Execution
    //
    // Verifies that the command forwards to migrate successfully.
    //

    describe('execution', function (): void {
        it('executes successfully with no options', function (): void {
            artisan('db:migrate')->assertSuccessful();
        });

        it('forwards common options correctly', function (): void {
            // Test pretend option (safe to run in tests)
            artisan('db:migrate', ['--pretend' => true])
                ->assertSuccessful();
        });

        it('forwards multiple options correctly', function (): void {
            // Test combination of options (all safe for tests)
            artisan('db:migrate', [
                '--pretend' => true,
                '--step' => true,
            ])->assertSuccessful();
        });

        it('handles path option as array', function (): void {
            // Test path option which accepts multiple values
            artisan('db:migrate', [
                '--pretend' => true,
                '--path' => ['database/migrations'],
            ])->assertSuccessful();
        });
    });
});
