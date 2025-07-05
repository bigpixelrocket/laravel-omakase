<?php

declare(strict_types=1);

// Ensure strict types are used throughout the package
arch('package uses strict types')
    ->expect('Bigpixelrocket\LaravelOmakase')
    ->toUseStrictTypes();

// Ensure commands extend the base Laravel Command class
arch('commands extend Laravel Command')
    ->expect('Bigpixelrocket\LaravelOmakase\Commands')
    ->toExtend(\Illuminate\Console\Command::class);

// Ensure service providers extend the base Laravel ServiceProvider
arch('service providers extend Laravel ServiceProvider')
    ->expect(\Bigpixelrocket\LaravelOmakase\OmakaseServiceProvider::class)
    ->toExtend(\Illuminate\Support\ServiceProvider::class);

// Ensure no debugging functions are left in the code
arch('no debug statements')
    ->expect('Bigpixelrocket\LaravelOmakase')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'die', 'exit']);

// Ensure the package doesn't depend on test code
arch('source code does not depend on tests')
    ->expect('Bigpixelrocket\LaravelOmakase')
    ->not->toUse('Tests');

// Ensure tests don't use inappropriate testing patterns
arch('tests follow best practices')
    ->expect('Tests')
    ->not->toUse(['ReflectionClass', 'ReflectionMethod'])
    ->ignoring('Tests\Feature\Commands\OmakaseCommandTest'); // Exception for package verification tests

// Ensure no raw file operations in source code (use facades)
arch('source code uses Laravel facades for file operations')
    ->expect('Bigpixelrocket\LaravelOmakase')
    ->not->toUse(['file_get_contents', 'file_put_contents', 'fopen', 'fwrite', 'unlink'])
    ->ignoring([
        \Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand::class, // copyFile method uses file_get_contents/file_put_contents for performance
    ]);

// Ensure commands have proper structure
arch('commands have handle method')
    ->expect('Bigpixelrocket\LaravelOmakase\Commands')
    ->toHaveMethod('handle');

// Ensure no direct file operations in tests (should use Laravel's File facade)
arch('tests use Laravel facades for file operations')
    ->expect('Tests')
    ->not->toUse(['file_get_contents', 'file_put_contents', 'fopen', 'fwrite', 'unlink']);
