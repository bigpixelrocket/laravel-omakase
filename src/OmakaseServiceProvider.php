<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase;

use Bigpixelrocket\LaravelOmakase\Commands\OmakaseCommand;
use Illuminate\Support\ServiceProvider;

class OmakaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                OmakaseCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        //
    }
}
