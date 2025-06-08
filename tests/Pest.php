<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function ($value) {
    return expect($value)->toBe(1);
});

/**
 * Create a temporary directory with automatic cleanup tracking.
 */
function createTempDirectory(string $prefix = 'laravel_omakase_test_'): string
{
    $dir = sys_get_temp_dir().'/'.$prefix.uniqid();
    \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);

    return $dir;
}

/**
 * Set application base path temporarily with automatic restoration.
 */
function withTemporaryBasePath(string $tempPath, callable $callback)
{
    $app = app();
    $original = $app->basePath();

    try {
        $app->setBasePath($tempPath);

        return $callback();
    } finally {
        $app->setBasePath($original);
    }
}
