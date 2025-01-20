<?php

namespace Bigpixelrocket\LaravelOmakase;

use Bigpixelrocket\LaravelOmakase\Commands\LaravelOmakaseCommand;
use Illuminate\Support\ServiceProvider;

class LaravelOmakaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelOmakaseCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        //
    }
}
