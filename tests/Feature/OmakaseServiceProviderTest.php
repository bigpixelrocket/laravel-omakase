<?php

declare(strict_types=1);

namespace Tests\Feature;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Bigpixelrocket\LaravelOmakase\OmakaseServiceProvider;
use Illuminate\Support\Facades\Artisan;

describe('OmakaseServiceProvider', function () {
    it('registers the OmakaseCommand when running in console', function () {
        // Create and boot a fresh provider
        $provider = new OmakaseServiceProvider(app());
        $provider->register();
        $provider->boot();

        // Verify the command is registered with correct signature
        $registeredCommands = Artisan::all();

        expect($registeredCommands)
            ->toHaveKey('laravel:omakase')
            ->and($registeredCommands['laravel:omakase'])
            ->toBeInstanceOf(OmakaseCommand::class);

        // Verify command properties
        $command = $registeredCommands['laravel:omakase'];
        expect($command->getName())->toBe('laravel:omakase')
            ->and($command->getDescription())->toBe('An opinionated menu for your next Laravel project');
    });

    it('does not register commands when not running in console', function () {
        // Store the current command list
        $commandsBefore = array_keys(Artisan::all());

        // Create a mock application that simulates not running in console
        $mockApp = $this->mock('Illuminate\Foundation\Application', function ($mock) {
            $mock->shouldReceive('runningInConsole')->andReturn(false);
            // The provider should not attempt to register commands
            $mock->shouldNotReceive('commands');
        });

        // Create and boot the provider
        $provider = new OmakaseServiceProvider($mockApp);
        $provider->boot();

        // Command list should remain unchanged
        expect(array_keys(Artisan::all()))->toBe($commandsBefore);
    });

    it('is registered as a service provider in the package', function () {
        // Verify the provider is properly loaded
        $loadedProviders = app()->getLoadedProviders();

        expect($loadedProviders)->toHaveKey(OmakaseServiceProvider::class)
            ->and($loadedProviders[OmakaseServiceProvider::class])->toBeTrue();
    });

    it('provides no services during registration', function () {
        $provider = new OmakaseServiceProvider(app());
        $provider->register();

        // The register method should be empty as per the implementation
        // This test ensures no services are bound during registration
        expect($provider->provides())->toBeEmpty();
    });
});
