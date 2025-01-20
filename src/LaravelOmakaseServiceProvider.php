<?php

namespace Bigpixelrocket\LaravelOmakase;

use Illuminate\Support\ServiceProvider;
use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;

class LaravelOmakaseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelOmakaseCommand::class,
            ]);
        }
    }

    public function register()
    {
        //
    }
}
