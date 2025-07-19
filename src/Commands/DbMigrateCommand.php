<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class DbMigrateCommand extends Command
{
    protected $signature = 'db:migrate {--migrate-help}';

    protected $description = 'Alias for the migrate command - Run the database migrations (use --migrate-help to see migrate options)';

    public function handle(): int
    {
        //
        // Help Command Handling
        // -------------------------------------------------------------------------------

        // Sadly the --help option never reaches the command, so we need a different help param and
        // we can't use $this->call(...) because it doesn't pass the --help option to the command
        // so we use Laravel's Process facade to run the migrate command with --help
        if ($this->option('migrate-help')) {
            try {
                // TTY mode will directly output to the terminal with proper formatting
                $result = Process::tty()->run(['php', 'artisan', 'migrate', '--help']);

                // TTY handles all output directly, so we just return the exit code
                return $result->exitCode() ?? 1;

            } catch (\Throwable) {
                // TTY not supported (like in test environments), fallback to regular process
                $result = Process::run(['php', 'artisan', 'migrate', '--help']);

                // In non-TTY mode, manually handle output based on success/failure
                if ($result->successful()) {
                    $this->output->write($result->output());

                    return 0;
                } else {
                    $this->output->write($result->errorOutput());

                    return $result->exitCode() ?? 1;
                }
            }
        }

        //
        // Delegate to Migrate Command
        // -------------------------------------------------------------------------------

        // Get all arguments and options from the input, excluding the command name itself
        $arguments = $this->input->getArguments();
        $options = $this->input->getOptions();

        // Build the parameters array for the migrate command
        $parameters = [];

        // Add all arguments (excluding the command name)
        foreach ($arguments as $key => $value) {
            if ($key !== 'command') {
                $parameters[$key] = $value;
            }
        }

        // Add all options, filtering out default/empty values and custom options
        foreach ($options as $key => $value) {
            // Skip our custom options that shouldn't be passed to migrate
            if ($key === 'migrate-help') {
                continue;
            }

            // Skip options that have their default values
            if ($value !== false && $value !== null && $value !== [] && $value !== '') {
                $parameters["--{$key}"] = $value;
            }
        }

        // Call the original migrate command with all passed arguments and options
        return $this->call('migrate', $parameters);
    }
}
