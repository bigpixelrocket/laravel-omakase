<?php

declare(strict_types=1);

use Bigpixelrocket\LaravelOmakase\Commands\DbMigrateCommand;
use Illuminate\Support\Facades\Process;

it('can be instantiated', function (): void {
    $command = new DbMigrateCommand;

    expect($command)->toBeInstanceOf(DbMigrateCommand::class);
});

it('has the correct signature', function (): void {
    $command = new DbMigrateCommand;

    expect($command->getName())->toBe('db:migrate');
});

it('has the correct description', function (): void {
    $command = new DbMigrateCommand;

    expect($command->getDescription())->toBe('Alias for the migrate command - Run the database migrations (use --migrate-help to see migrate options)');
});

it('calls the migrate command when executed', function (): void {
    // Test the basic functionality without options since dynamic forwarding
    // means we can't pass unregistered options in tests
    $this->artisan('db:migrate')
        ->assertExitCode(0);
});

it('is registered as a console command', function (): void {
    $commands = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();

    expect($commands)->toHaveKey('db:migrate');
});

it('has exactly one parameter called --migrate-help', function (): void {
    $command = new DbMigrateCommand;

    // Get the command definition to inspect options
    $definition = $command->getDefinition();
    $options = $definition->getOptions();

    // Filter out the default Laravel command options to get only the custom ones
    $defaultOptions = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'];
    $customOptions = array_filter($options, function ($key) use ($defaultOptions) {
        return ! in_array($key, $defaultOptions);
    }, ARRAY_FILTER_USE_KEY);

    // Should have exactly one custom option
    expect($customOptions)->toHaveCount(1);

    // That option should be 'migrate-help'
    expect($customOptions)->toHaveKey('migrate-help');
});

it('forwards help requests to the migrate command', function (): void {
    // Mock the Process facade to verify the correct command is called
    Process::fake([
        '*' => Process::result(
            output: 'Migration help output',
            exitCode: 0
        ),
    ]);

    // Execute the command with the migrate-help option
    $this->artisan('db:migrate', ['--migrate-help' => true])
        ->assertExitCode(0);

    // Verify that the Process facade was called with the correct command
    Process::assertRan(function ($process): bool {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'php artisan migrate --help');
    });
});
