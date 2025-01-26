<?php

namespace Tests\Feature\Commands;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Mockery;

use function Pest\Laravel\artisan;

beforeEach(function () {
    // Create a partial mock of the OmakaseCommand
    $command = Mockery::mock(OmakaseCommand::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    // Mock the protected methods that execute commands
    $command->shouldReceive('installPackages')->andReturn(true);
    $command->shouldReceive('execCommands')->andReturn(true);
    $command->shouldReceive('exec')->andReturn(true);

    // Let the constructor and configure methods run normally
    $command->shouldReceive('configure')
        ->passthru();

    // Call the parent constructor explicitly
    $command->__construct();

    // Bind the mock to the container
    app()->instance(OmakaseCommand::class, $command);
});

it('installs packages and copies files', function () {
    $command = app(OmakaseCommand::class);

    artisan(OmakaseCommand::class)
        ->expectsOutputToContain('Installing Composer Packages')
        ->expectsOutputToContain('Installing NPM Packages')
        ->expectsOutputToContain('Copying files')
        ->assertSuccessful();

    // Verify installPackages was called for both composer and npm
    $command->shouldHaveReceived('installPackages')->twice();
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
});
