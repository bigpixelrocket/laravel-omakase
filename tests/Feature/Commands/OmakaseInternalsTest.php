<?php

namespace Tests\Feature\Commands;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Illuminate\Support\Facades\File;
use Mockery;

use function callProtectedMethod;
use function Pest\Laravel\artisan;

it('returns sorted file list from getDistFiles', function () {
    $dir = sys_get_temp_dir().'/dist_'.uniqid();
    File::ensureDirectoryExists("{$dir}/b");
    File::ensureDirectoryExists("{$dir}/a");
    file_put_contents("{$dir}/b/two.txt", '2');
    file_put_contents("{$dir}/a/one.txt", '1');

    $command = new OmakaseCommand();
    $files = callProtectedMethod($command, 'getDistFiles', [$dir.'/']);

    expect($files)->toBe([
        "{$dir}/a/one.txt",
        "{$dir}/b/two.txt",
    ]);

    File::deleteDirectory($dir);
});

it('creates directories when copying files', function () {
    $dir = sys_get_temp_dir().'/copy_'.uniqid();
    File::ensureDirectoryExists($dir);
    $source = "{$dir}/src.txt";
    $destDir = "{$dir}/nested";
    $dest = "{$destDir}/dest.txt";
    file_put_contents($source, 'abc');

    $command = new OmakaseCommand();
    callProtectedMethod($command, 'copyFile', [$source, $dest, $destDir]);

    expect(File::exists($dest))->toBeTrue();
    expect(file_get_contents($dest))->toBe('abc');

    File::deleteDirectory($dir);
});

it('builds expected commands in installPackages', function () {
    $command = Mockery::mock(OmakaseCommand::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $packages = [
        'require' => [
            'foo/bar',
            'livewire/livewire' => [
                ['php', 'artisan', 'livewire:publish', '--config'],
            ],
        ],
        'require-dev' => [
            'phpunit/phpunit',
        ],
    ];

    $expectedRequire = [
        ['composer', 'require', 'foo/bar', 'livewire/livewire'],
        ['php', 'artisan', 'livewire:publish', '--config'],
    ];

    $expectedDev = [
        ['composer', 'require', '--dev', 'phpunit/phpunit'],
    ];

    $command->shouldReceive('execCommands')->with($expectedRequire)->once()->andReturn(true);
    $command->shouldReceive('execCommands')->with($expectedDev)->once()->andReturn(true);

    $result = callProtectedMethod($command, 'installPackages', [$packages, ['composer', 'require'], 'require-dev', '--dev']);

    expect($result)->toBeTrue();

    $command->shouldHaveReceived('execCommands')->with($expectedRequire)->once();
    $command->shouldHaveReceived('execCommands')->with($expectedDev)->once();
});

it('copies dist files respecting the force option', function () {
    $base = sys_get_temp_dir().'/base_'.uniqid();
    File::ensureDirectoryExists($base);

    $app = app();
    $original = $app->basePath();
    $app->setBasePath($base);

    try {
        artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();
        expect(File::exists("{$base}/phpstan.neon"))->toBeTrue();
        expect(File::exists("{$base}/pint.json"))->toBeTrue();

        $originalContent = file_get_contents("{$base}/pint.json");
        artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();

        file_put_contents("{$base}/pint.json", 'changed');
        artisan(OmakaseCommand::class, ['--files' => true, '--force' => true])->assertSuccessful();
        expect(file_get_contents("{$base}/pint.json"))->toBe($originalContent);
    } finally {
        $app->setBasePath($original);
        File::deleteDirectory($base);
    }
});

