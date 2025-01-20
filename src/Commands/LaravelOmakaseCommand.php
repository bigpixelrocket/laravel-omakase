<?php

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;

class LaravelOmakaseCommand extends Command
{
    protected $signature = 'laravelomakase';

    protected $description = 'Run the omakase command';

    public function handle()
    {
        $this->info('Hello Omakase!');

        return self::SUCCESS;
    }
}
