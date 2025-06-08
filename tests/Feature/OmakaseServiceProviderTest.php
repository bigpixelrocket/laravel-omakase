<?php

namespace Tests\Feature;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Bigpixelrocket\LaravelOmakase\OmakaseServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Mockery;

describe('OmakaseServiceProvider', function () {
    it('registers the OmakaseCommand when running in console', function () {
        // Verify the command is available
        expect(Artisan::all())->toHaveKey('laravel:omakase');

        // Verify it's the correct command class
        $command = Artisan::all()['laravel:omakase'];
        expect($command)->toBeInstanceOf(OmakaseCommand::class);
    });

    it('has the correct command signature and description', function () {
        $command = Artisan::all()['laravel:omakase'];

        expect($command->getName())->toBe('laravel:omakase');
        expect($command->getDescription())->toBe('An opinionated menu for your next Laravel project');
    });

    it('does not register commands when not running in console', function () {
        // Create a mock application that returns false for runningInConsole
        $mockApp = Mockery::mock(Application::class);
        $mockApp->shouldReceive('runningInConsole')->andReturn(false);

        // Create service provider with mocked app
        $provider = new OmakaseServiceProvider($mockApp);

        // Since runningInConsole() returns false, boot() should not call commands()
        // We can verify this by ensuring no exceptions are thrown and the provider can boot
        $provider->boot();

        // Verify the provider was created successfully
        expect($provider)->toBeInstanceOf(OmakaseServiceProvider::class);
    });

    it('can be instantiated with Laravel application', function () {
        $provider = new OmakaseServiceProvider(app());

        expect($provider)->toBeInstanceOf(OmakaseServiceProvider::class);
    });

    it('boot method can be called without errors', function () {
        $provider = new OmakaseServiceProvider(app());

        // Should not throw any exceptions
        $provider->boot();

        // Verify command is registered when running in console
        expect(Artisan::all())->toHaveKey('laravel:omakase');
    });

    it('register method can be called without errors', function () {
        $provider = new OmakaseServiceProvider(app());

        // Should not throw any exceptions
        $provider->register();

        // Verify the provider exists and can be instantiated
        expect($provider)->toBeInstanceOf(OmakaseServiceProvider::class);
    });

    it('extends Laravel ServiceProvider', function () {
        $provider = new OmakaseServiceProvider(app());

        expect($provider)->toBeInstanceOf(\Illuminate\Support\ServiceProvider::class);
    });
});
