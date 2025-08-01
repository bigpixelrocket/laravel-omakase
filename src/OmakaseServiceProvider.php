<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase;

use Bigpixelrocket\LaravelOmakase\Commands\DbMigrateCommand;
use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Illuminate\Support\ServiceProvider;

class OmakaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DbMigrateCommand::class,
                OmakaseCommand::class,
            ]);
        }
    }

    #[\Override]
    public function register(): void
    {
        //
        // Package Configuration Registration
        // -------------------------------------------------------------------------------

        // Register internal package configurations
        // These are not meant to be published/customized by users
        $this->mergeConfigFrom(
            __DIR__.'/../config/composer-packages.php',
            'laravel-omakase.composer-packages'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/npm-packages.php',
            'laravel-omakase.npm-packages'
        );
    }
}
