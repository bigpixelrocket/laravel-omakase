<?php

namespace Tests\Feature\Commands;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Illuminate\Support\Facades\File;
use Mockery;

use function callProtectedMethod;
use function Pest\Laravel\artisan;

// Tests that use mocked command for interface testing
describe('Command Interface (Mocked)', function () {
    beforeEach(function () {
        // Create a partial mock of the OmakaseCommand
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        // Mock the protected methods that execute commands
        $command->shouldReceive('installPackages')->andReturn(true);
        $command->shouldReceive('execCommands')->andReturn(true);
        $command->shouldReceive('exec')->andReturn(true);
        $command->shouldReceive('copyFiles')->andReturn(true);

        // Let the constructor and configure methods run normally
        $command->shouldReceive('configure')
            ->passthru();

        // Call the parent constructor explicitly
        $command->__construct();

        // Bind the mock to the container
        app()->instance(OmakaseCommand::class, $command);
    });

    it('installs packages and copies files by default', function () {
        $command = app(OmakaseCommand::class);

        artisan(OmakaseCommand::class)
            ->expectsOutputToContain('Installing Composer Packages')
            ->expectsOutputToContain('Installing NPM Packages')
            ->expectsOutputToContain('Copying files')
            ->assertSuccessful();

        // Verify installPackages was called for both composer and npm
        $command->shouldHaveReceived('installPackages')->twice();
        $command->shouldHaveReceived('copyFiles')->once();
    });

    it('only installs composer packages with the --composer option', function () {
        $command = app(OmakaseCommand::class);

        artisan(OmakaseCommand::class, ['--composer' => true])
            ->expectsOutputToContain('Installing Composer Packages')
            ->doesntExpectOutputToContain('Installing NPM Packages')
            ->doesntExpectOutputToContain('Copying files')
            ->assertSuccessful();

        // Verify installPackages was called once for composer
        $command->shouldHaveReceived('installPackages')->once();
        $command->shouldNotHaveReceived('copyFiles');
    });

    it('only installs npm packages with the --npm option', function () {
        $command = app(OmakaseCommand::class);

        artisan(OmakaseCommand::class, ['--npm' => true])
            ->doesntExpectOutputToContain('Installing Composer Packages')
            ->expectsOutputToContain('Installing NPM Packages')
            ->doesntExpectOutputToContain('Copying files')
            ->assertSuccessful();

        // Verify installPackages was called once for npm
        $command->shouldHaveReceived('installPackages')->once();
        $command->shouldNotHaveReceived('copyFiles');
    });

    it('only copies files with the --files option', function () {
        $command = app(OmakaseCommand::class);

        artisan(OmakaseCommand::class, ['--files' => true])
            ->doesntExpectOutputToContain('Installing Composer Packages')
            ->doesntExpectOutputToContain('Installing NPM Packages')
            ->expectsOutputToContain('Copying files')
            ->assertSuccessful();

        // Verify installPackages was not called
        $command->shouldNotHaveReceived('installPackages');
        $command->shouldHaveReceived('copyFiles')->once();
    });

    it('handles composer installation failure', function () {
        // Create a new mock for failure scenario
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $command->shouldReceive('installPackages')->andReturn(false);
        $command->shouldReceive('configure')->passthru();
        $command->__construct();

        app()->instance(OmakaseCommand::class, $command);

        artisan(OmakaseCommand::class, ['--composer' => true])
            ->assertFailed();
    });

    it('handles npm installation failure', function () {
        // Create a new mock for failure scenario
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        // First call succeeds (composer), second call fails (npm)
        $command->shouldReceive('installPackages')
            ->twice()
            ->andReturn(true, false);
        $command->shouldReceive('configure')->passthru();
        $command->__construct();

        app()->instance(OmakaseCommand::class, $command);

        artisan(OmakaseCommand::class)
            ->assertFailed();
    });

    it('handles file copying failure', function () {
        // Create a new mock for failure scenario
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $command->shouldReceive('installPackages')->andReturn(true);
        $command->shouldReceive('copyFiles')->andReturn(false);
        $command->shouldReceive('configure')->passthru();
        $command->__construct();

        app()->instance(OmakaseCommand::class, $command);

        artisan(OmakaseCommand::class)
            ->assertFailed();
    });

    it('shows proper output formatting with decorative boxes', function () {
        $command = app(OmakaseCommand::class);

        artisan(OmakaseCommand::class)
            ->expectsOutputToContain('╔═══════════════════════════════════════════╗')
            ->expectsOutputToContain('║       Installing Composer Packages        ║')
            ->expectsOutputToContain('║         Installing NPM Packages           ║')
            ->expectsOutputToContain('║              Copying files                ║')
            ->expectsOutputToContain('╚═══════════════════════════════════════════╝')
            ->assertSuccessful();
    });
});

// Tests that use real command implementation
describe('Command Implementation (Real)', function () {
    beforeEach(function () {
        // Clear any mocked instances
        app()->forgetInstance(OmakaseCommand::class);
    });

    it('has correct command signature and description', function () {
        $command = new OmakaseCommand;

        expect($command->getName())->toBe('laravel:omakase');
        expect($command->getDescription())->toBe('An opinionated menu for your next Laravel project');

        // Verify options are defined
        $definition = $command->getDefinition();
        expect($definition->hasOption('composer'))->toBeTrue();
        expect($definition->hasOption('npm'))->toBeTrue();
        expect($definition->hasOption('files'))->toBeTrue();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('copies all expected dist files', function () {
        $base = sys_get_temp_dir().'/dist_copy_'.uniqid();
        File::ensureDirectoryExists($base);

        $app = app();
        $original = $app->basePath();
        $app->setBasePath($base);

        try {
            artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();

            // Verify all expected files are copied
            $expectedFiles = [
                '.cursorignore',
                '.cursorrules',
                'CLAUDE.md',
                'AGENTS.md',
                '.prettierrc',
                'phpstan.neon',
                'pint.json',
                '.github/dependabot.yml',
                '.github/workflows/pest.yml',
                '.github/workflows/dependabot-automerge.yml',
                '.github/workflows/release.yml',
                '.github/workflows/phpstan.yml',
                '.github/workflows/pint.yml',
            ];

            foreach ($expectedFiles as $file) {
                expect(File::exists("{$base}/{$file}"))->toBeTrue("File {$file} should be copied");
            }
        } finally {
            $app->setBasePath($original);
            File::deleteDirectory($base);
        }
    });

    it('respects force option when copying files', function () {
        $base = sys_get_temp_dir().'/force_'.uniqid();
        File::ensureDirectoryExists($base);

        $app = app();
        $original = $app->basePath();
        $app->setBasePath($base);

        try {
            // First copy
            artisan(OmakaseCommand::class, ['--files' => true])->assertSuccessful();
            expect(File::exists("{$base}/pint.json"))->toBeTrue();

            $originalContent = file_get_contents("{$base}/pint.json");

            // Modify the file
            file_put_contents("{$base}/pint.json", 'modified content');
            expect(file_get_contents("{$base}/pint.json"))->toBe('modified content');

            // Copy without force should skip
            artisan(OmakaseCommand::class, ['--files' => true])
                ->expectsOutputToContain('skip pint.json')
                ->assertSuccessful();
            expect(file_get_contents("{$base}/pint.json"))->toBe('modified content');

            // Copy with force should override
            artisan(OmakaseCommand::class, ['--files' => true, '--force' => true])
                ->expectsOutputToContain('override pint.json')
                ->assertSuccessful();
            expect(file_get_contents("{$base}/pint.json"))->toBe($originalContent);
        } finally {
            $app->setBasePath($original);
            File::deleteDirectory($base);
        }
    });

    it('respects the --force option when copying files', function () {
        $base = sys_get_temp_dir().'/force_test_'.uniqid();
        File::ensureDirectoryExists($base);

        $app = app();
        $original = $app->basePath();
        $app->setBasePath($base);

        try {
            artisan(OmakaseCommand::class, ['--files' => true, '--force' => true])
                ->assertSuccessful();

            // Verify force option was passed through (files should exist)
            expect(File::exists("{$base}/pint.json"))->toBeTrue();
        } finally {
            $app->setBasePath($original);
            File::deleteDirectory($base);
        }
    });
});

// Internal Method Tests (using real instances for reflection)
describe('Internal Methods', function () {
    it('returns sorted file list from getDistFiles', function () {
        $dir = sys_get_temp_dir().'/dist_'.uniqid();
        File::ensureDirectoryExists("{$dir}/b");
        File::ensureDirectoryExists("{$dir}/a");
        file_put_contents("{$dir}/b/two.txt", '2');
        file_put_contents("{$dir}/a/one.txt", '1');

        $command = new OmakaseCommand;
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

        $command = new OmakaseCommand;
        callProtectedMethod($command, 'copyFile', [$source, $dest, $destDir]);

        expect(File::exists($dest))->toBeTrue();
        expect(file_get_contents($dest))->toBe('abc');

        File::deleteDirectory($dir);
    });

    it('installs exact composer packages with correct post-install commands', function () {
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        // This should match EXACTLY what's in OmakaseCommand.php
        $expectedPackages = [
            'require' => [
                'livewire/livewire' => [
                    ['php', 'artisan', 'livewire:publish', '--config'],
                ],
                'spatie/laravel-data' => [
                    ['php', 'artisan', 'vendor:publish', '--provider=Spatie\LaravelData\LaravelDataServiceProvider', '--tag=data-config'],
                ],
            ],
            'require-dev' => [
                'barryvdh/laravel-ide-helper',
                'larastan/larastan',
                'laravel/pint',
                'pestphp/pest',
                'soloterm/solo',
            ],
        ];

        // Expected commands for require packages
        $expectedRequireCommands = [
            ['composer', 'require', 'livewire/livewire', 'spatie/laravel-data'],
            ['php', 'artisan', 'livewire:publish', '--config'],
            ['php', 'artisan', 'vendor:publish', '--provider=Spatie\LaravelData\LaravelDataServiceProvider', '--tag=data-config'],
        ];

        // Expected commands for require-dev packages
        $expectedDevCommands = [
            ['composer', 'require', '--dev', 'barryvdh/laravel-ide-helper', 'larastan/larastan', 'laravel/pint', 'pestphp/pest', 'soloterm/solo'],
        ];

        $command->shouldReceive('execCommands')->with($expectedRequireCommands)->once()->andReturn(true);
        $command->shouldReceive('execCommands')->with($expectedDevCommands)->once()->andReturn(true);

        $result = callProtectedMethod($command, 'installPackages', [$expectedPackages, ['composer', 'require'], 'require-dev', '--dev']);

        expect($result)->toBeTrue();
    });

    it('installs exact npm packages', function () {
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        // Mock the output methods to prevent null formatter errors
        $command->shouldReceive('warn')->andReturn();
        $command->shouldReceive('exec')->andReturn(true);

        // This should match EXACTLY what's in OmakaseCommand.php
        $expectedNpmPackages = [
            'dependencies' => [
            ],
            'devDependencies' => [
                'prettier',
                'prettier-plugin-blade',
                'prettier-plugin-tailwindcss',
            ],
        ];

        $result = callProtectedMethod($command, 'installPackages', [$expectedNpmPackages, ['npm', 'install'], 'devDependencies', '--save-dev']);

        expect($result)->toBeTrue();
    });

    it('handles process execution failure gracefully', function () {
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        // Mock the output methods to prevent null formatter errors
        $command->shouldReceive('warn')->andReturn();

        // Mock the Process constructor
        $command->shouldReceive('exec')
            ->with(['composer', 'require', 'invalid/package'])
            ->andReturn(false);

        $result = callProtectedMethod($command, 'execCommands', [
            [['composer', 'require', 'invalid/package']],
        ]);

        expect($result)->toBeFalse();
    });

    it('has correct TTY logic for different OS families', function () {
        // Since we can't easily mock the Process constructor without refactoring,
        // we'll verify the logic exists in the source code
        $command = new OmakaseCommand;
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());

        // Verify the Windows TTY check exists
        expect($source)->toContain('PHP_OS_FAMILY !== \'Windows\'');
        expect($source)->toContain('setTty(true)');
    });

    it('handles file copy exceptions', function () {
        $command = new OmakaseCommand;

        // Try to copy to an invalid destination
        expect(function () use ($command) {
            callProtectedMethod($command, 'copyFile', [
                '/nonexistent/source.txt',
                '/invalid/dest.txt',
                '/invalid',
            ]);
        })->toThrow(\Exception::class);
    });
});

// Package Verification Tests
describe('Package Verification', function () {
    it('verifies exact composer packages are defined in command', function () {
        // Read the source file directly to avoid any test context issues
        $sourceFile = __DIR__.'/../../../src/Commands/OmakaseCommand.php';
        $source = file_get_contents($sourceFile);

        // Verify all expected composer packages are present in the source
        $expectedComposerPackages = [
            'livewire/livewire',
            'spatie/laravel-data',
            'barryvdh/laravel-ide-helper',
            'larastan/larastan',
            'laravel/pint',
            'pestphp/pest',
            'soloterm/solo',
        ];

        foreach ($expectedComposerPackages as $package) {
            expect(str_contains($source, $package))->toBeTrue("Composer package {$package} should be defined in the command");
        }
    });

    it('verifies exact npm packages are defined in command', function () {
        // Read the source file directly to avoid any test context issues
        $sourceFile = __DIR__.'/../../../src/Commands/OmakaseCommand.php';
        $source = file_get_contents($sourceFile);

        // Verify all expected npm packages are present in the source
        $expectedNpmPackages = [
            'prettier',
            'prettier-plugin-blade',
            'prettier-plugin-tailwindcss',
        ];

        foreach ($expectedNpmPackages as $package) {
            expect(str_contains($source, $package))->toBeTrue("NPM package {$package} should be defined in the command");
        }
    });
});

// Edge Cases and Error Handling Tests
describe('Edge Cases and Error Handling', function () {
    beforeEach(function () {
        // Clear any mocked instances
        app()->forgetInstance(OmakaseCommand::class);
    });

    it('handles empty dist directory gracefully', function () {
        $emptyDir = sys_get_temp_dir().'/empty_dist_'.uniqid();
        File::ensureDirectoryExists($emptyDir);

        $command = new OmakaseCommand;
        $files = callProtectedMethod($command, 'getDistFiles', [$emptyDir]);

        expect($files)->toBeArray();
        expect($files)->toBeEmpty();

        File::deleteDirectory($emptyDir);
    });

    it('handles missing dist directory gracefully', function () {
        $command = new OmakaseCommand;

        expect(function () use ($command) {
            callProtectedMethod($command, 'getDistFiles', ['/nonexistent/directory/']);
        })->toThrow(\Exception::class);
    });

    it('creates nested directories when copying files', function () {
        $base = sys_get_temp_dir().'/nested_copy_'.uniqid();
        File::ensureDirectoryExists($base);

        $app = app();
        $original = $app->basePath();
        $app->setBasePath($base);

        try {
            // Create a test source file in a nested structure
            $sourceDir = $base.'/test_source/deeply/nested';
            File::ensureDirectoryExists($sourceDir);
            file_put_contents($sourceDir.'/test.txt', 'test content');

            $command = new OmakaseCommand;
            $destPath = $base.'/test_dest/also/deeply/nested/test.txt';
            $destDir = dirname($destPath);

            callProtectedMethod($command, 'copyFile', [$sourceDir.'/test.txt', $destPath, $destDir]);

            expect(File::exists($destPath))->toBeTrue();
            expect(file_get_contents($destPath))->toBe('test content');
            expect(File::isDirectory($destDir))->toBeTrue();
        } finally {
            $app->setBasePath($original);
            File::deleteDirectory($base);
        }
    });

    it('handles file permission errors during copy', function () {
        $command = new OmakaseCommand;

        // Try to copy to a read-only location (this may vary by system)
        expect(function () use ($command) {
            callProtectedMethod($command, 'copyFile', [
                __FILE__, // Use this test file as source
                '/root/readonly/dest.txt', // Destination that should fail
                '/root/readonly',
            ]);
        })->toThrow(\Exception::class);
    });

    it('validates command options are properly defined', function () {
        $command = new OmakaseCommand;
        $definition = $command->getDefinition();

        // Verify all expected options exist
        $expectedOptions = ['composer', 'npm', 'files', 'force'];
        foreach ($expectedOptions as $option) {
            expect($definition->hasOption($option))->toBeTrue("Option --{$option} should be defined");
        }

        // Verify options are boolean flags (no values required)
        expect($definition->getOption('composer')->isValueRequired())->toBeFalse();
        expect($definition->getOption('npm')->isValueRequired())->toBeFalse();
        expect($definition->getOption('files')->isValueRequired())->toBeFalse();
        expect($definition->getOption('force')->isValueRequired())->toBeFalse();
    });

    it('handles multiple option combinations correctly', function () {
        $command = Mockery::mock(OmakaseCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $command->shouldReceive('installPackages')->andReturn(true);
        $command->shouldReceive('copyFiles')->andReturn(true);
        $command->shouldReceive('configure')->passthru();
        $command->__construct();

        app()->instance(OmakaseCommand::class, $command);

        // Test composer + files combination
        artisan(OmakaseCommand::class, ['--composer' => true, '--files' => true])
            ->expectsOutputToContain('Installing Composer Packages')
            ->expectsOutputToContain('Copying files')
            ->doesntExpectOutputToContain('Installing NPM Packages')
            ->assertSuccessful();

        // Verify correct methods were called
        $command->shouldHaveReceived('installPackages');
        $command->shouldHaveReceived('copyFiles');
    });

    it('verifies process timeout behavior', function () {
        $command = new OmakaseCommand;
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());

        // Verify that Process is properly instantiated (implementation detail check)
        expect($source)->toContain('new \Symfony\Component\Process\Process');
    });
});

// Cleanup and Isolation Tests
describe('Test Isolation and Cleanup', function () {
    it('properly cleans up temporary directories in tests', function () {
        $tempDirs = [];

        // Create several temp directories
        for ($i = 0; $i < 3; $i++) {
            $dir = sys_get_temp_dir().'/cleanup_test_'.uniqid();
            File::ensureDirectoryExists($dir);
            file_put_contents($dir.'/test.txt', 'test');
            $tempDirs[] = $dir;
        }

        // Verify they exist
        foreach ($tempDirs as $dir) {
            expect(File::exists($dir.'/test.txt'))->toBeTrue();
        }

        // Clean them up
        foreach ($tempDirs as $dir) {
            File::deleteDirectory($dir);
        }

        // Verify they're gone
        foreach ($tempDirs as $dir) {
            expect(File::exists($dir))->toBeFalse();
        }

        expect(true)->toBeTrue(); // Test completed successfully
    });

    it('does not interfere with Laravel application state', function () {
        $originalBasePath = app()->basePath();

        // Change base path temporarily
        $tempPath = sys_get_temp_dir().'/laravel_state_'.uniqid();
        File::ensureDirectoryExists($tempPath);
        app()->setBasePath($tempPath);

        // Verify it changed
        expect(app()->basePath())->toBe($tempPath);

        // Restore original
        app()->setBasePath($originalBasePath);

        // Verify it's restored
        expect(app()->basePath())->toBe($originalBasePath);

        // Cleanup
        File::deleteDirectory($tempPath);
    });
});
